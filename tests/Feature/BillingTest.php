<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Renter;
use App\Models\Unit;
use App\Notifications\ReceiptIssuedNotification;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->clientA = Client::create(['slug' => 'a', 'name' => 'Client A', 'status' => 'active']);
    $this->clientB = Client::create(['slug' => 'b', 'name' => 'Client B', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Helper: create the upstream graph (Location → Property → Unit → Renter →
 * active Lease) inside the active tenant and return the lease so each test
 * can focus on billing.
 */
function makeLease(int $rentCents = 350_000_00): Lease
{
    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $prop = Property::create([
        'location_id' => $loc->id,
        'name' => 'P',
        'type' => 'residential',
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'property_id' => $prop->id,
        'code' => 'U1',
        'type' => 'room',
        'rent_amount' => $rentCents,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'status' => 'vacant',
    ]);
    $renter = Renter::create([
        'type' => 'individual',
        'full_name' => 'Test Renter',
        'phone' => '+255712345678',
        'email' => 'renter@example.com',
    ]);
    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => $unit->rent_amount,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'pending',
    ]);
    $lease->activate();

    return $lease;
}

function makeInvoice(Lease $lease, int $rentCents = 350_000_00, ?string $dueDate = null): Invoice
{
    $invoice = Invoice::create([
        'lease_id' => $lease->id,
        'billing_period_start' => now()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => $dueDate ?? now()->addDays(7)->toDateString(),
        'currency' => 'TZS',
        'status' => 'draft',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Rent',
        'type' => 'rent',
        'quantity' => 1,
        'unit_price' => $rentCents,
    ]);

    return $invoice->fresh();
}

it('generates sequential invoice numbers per tenant + year', function () {
    Tenancy::initialize($this->clientA);

    $gen = app(InvoiceNumberGenerator::class);
    $a1 = $gen->next($this->clientA->id, 2026);
    $a2 = $gen->next($this->clientA->id, 2026);
    $a3 = $gen->next($this->clientA->id, 2026);

    expect($a1)->toBe('INV-A-2026-000001');
    expect($a2)->toBe('INV-A-2026-000002');
    expect($a3)->toBe('INV-A-2026-000003');
});

it('keeps invoice number sequences isolated per client', function () {
    $gen = app(InvoiceNumberGenerator::class);

    $aFirst = $gen->next($this->clientA->id, 2026);
    $bFirst = $gen->next($this->clientB->id, 2026);
    $aSecond = $gen->next($this->clientA->id, 2026);

    expect($aFirst)->toBe('INV-A-2026-000001');
    expect($bFirst)->toBe('INV-B-2026-000001');
    expect($aSecond)->toBe('INV-A-2026-000002');
});

it('issues a draft invoice and assigns it a number', function () {
    Tenancy::initialize($this->clientA);

    $lease = makeLease();
    $invoice = makeInvoice($lease);

    expect($invoice->status)->toBe('draft');
    expect($invoice->invoice_number)->toBeNull();
    expect($invoice->total_amount)->toBe(350_000_00);

    $invoice->issue();
    $invoice->refresh();

    expect($invoice->status)->toBe('unpaid');
    expect($invoice->invoice_number)->toMatch('/^INV-A-\d{4}-\d{6}$/');
    expect($invoice->issued_at)->not->toBeNull();
});

it('refuses to issue an invoice with no items', function () {
    Tenancy::initialize($this->clientA);

    $lease = makeLease();
    $invoice = Invoice::create([
        'lease_id' => $lease->id,
        'billing_period_start' => now()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency' => 'TZS',
        'status' => 'draft',
    ]);

    expect(fn () => $invoice->issue())->toThrow(RuntimeException::class);
});

it('promotes invoice from unpaid → partial → paid as payments accumulate', function () {
    Tenancy::initialize($this->clientA);
    Notification::fake();

    $lease = makeLease();
    $invoice = makeInvoice($lease);
    $invoice->issue();

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('partial');
    expect($invoice->amount_paid)->toBe(100_000_00);

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 250_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'mobile_money',
        'mobile_money_provider' => 'mpesa',
        'status' => 'completed',
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->amount_paid)->toBe(350_000_00);
    expect($invoice->paid_at)->not->toBeNull();
});

it('issues a receipt and sends the email when a completed payment lands', function () {
    Tenancy::initialize($this->clientA);
    Notification::fake();

    $lease = makeLease();
    $invoice = makeInvoice($lease);
    $invoice->issue();

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 350_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    expect(Receipt::count())->toBe(1);

    $receipt = Receipt::first();
    expect($receipt->receipt_number)->toMatch('/^RCP-A-\d{4}-\d{6}$/');

    Notification::assertSentOnDemand(ReceiptIssuedNotification::class);
});

it('does not issue a receipt for pending payments', function () {
    Tenancy::initialize($this->clientA);

    $lease = makeLease();
    $invoice = makeInvoice($lease);
    $invoice->issue();

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'mobile_money',
        'mobile_money_provider' => 'mpesa',
        'status' => 'pending',
    ]);

    expect(Receipt::count())->toBe(0);
});

it('recomputes invoice status downward when a payment is deleted', function () {
    Tenancy::initialize($this->clientA);
    Notification::fake();

    $lease = makeLease();
    $invoice = makeInvoice($lease);
    $invoice->issue();

    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 350_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');

    $payment->delete();

    $invoice->refresh();
    expect($invoice->status)->toBe('unpaid');
    expect($invoice->amount_paid)->toBe(0);
});

it('promotes past-due invoices to overdue via the scheduler command', function () {
    Tenancy::initialize($this->clientA);

    $lease = makeLease();
    $invoice = makeInvoice($lease, dueDate: now()->subDays(3)->toDateString());
    $invoice->issue();

    Tenancy::end();

    $this->artisan('billing:detect-overdue')->assertExitCode(0);

    Tenancy::initialize($this->clientA);
    expect($invoice->fresh()->status)->toBe('overdue');
});

it('keeps invoices isolated between clients', function () {
    Tenancy::initialize($this->clientA);
    $leaseA = makeLease();
    makeInvoice($leaseA);

    Tenancy::end();
    Tenancy::initialize($this->clientB);

    expect(Invoice::count())->toBe(0);
});

it('refuses to cancel an invoice with completed payments', function () {
    Tenancy::initialize($this->clientA);
    Notification::fake();

    $lease = makeLease();
    $invoice = makeInvoice($lease);
    $invoice->issue();

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 100_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    expect(fn () => $invoice->cancel())->toThrow(RuntimeException::class);
});
