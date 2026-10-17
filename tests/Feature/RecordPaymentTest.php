<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\Dashboard;
use App\Filament\Operator\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Operator\Resources\Renters\Pages\EditRenter;
use App\Filament\Operator\Widgets\NeedsAttentionWidget;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'payco', 'name' => 'Pay Rentals', 'status' => 'active']);
    $this->other = Client::create(['slug' => 'otherpay', 'name' => 'Other Pay', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina',
        'email' => 'amina@pay.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** An overdue TZS 500,000 invoice for a renter in $client's workspace. */
function payInvoice(Client $client, string $name = 'Hassan Omar'): Invoice
{
    tenancy()->initialize($client);

    $location = Location::firstOrCreate(['name' => 'Area '.$client->id], ['region' => 'Dar', 'district' => 'Ilala']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Block', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'A'.random_int(1, 999), 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 500_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => $name, 'phone' => '+2557'.random_int(10000000, 99999999)]);
    $lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => now()->subMonths(3)->toDateString(), 'rent_amount' => 500_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    $invoice = new Invoice([
        'lease_id' => $lease->id, 'invoice_number' => 'INV-'.random_int(1000, 9999), 'currency' => 'TZS',
        'subtotal' => 500_000_00, 'total_amount' => 500_000_00,
        'billing_period_start' => now()->subMonth()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->subMonth()->endOfMonth()->toDateString(),
        'due_date' => now()->subDays(5)->toDateString(),
    ]);
    $invoice->status = Invoice::STATUS_OVERDUE;
    $invoice->save();

    Tenancy::end();

    return $invoice;
}

function payAs(User $user, Client $client): void
{
    test()->actingAs($user, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($client);
}

it('records an M-Pesa payment from an invoice row in one step and issues a receipt', function () {
    $invoice = payInvoice($this->client);
    payAs($this->owner, $this->client);

    Livewire::test(ListInvoices::class)
        ->mountAction(TestAction::make('recordPayment')->table($invoice))
        ->assertSchemaStateSet(['amount' => 500_000, 'channel' => Payment::METHOD_CASH], 'mountedActionSchema0')
        ->setActionData(['amount' => 200_000, 'channel' => Payment::PROVIDER_MPESA, 'reference' => 'QJK7X2PLM9'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Payment recorded');

    $payment = Payment::query()->where('invoice_id', $invoice->id)->sole();

    expect($payment->amount)->toBe(200_000_00)
        ->and($payment->method)->toBe(Payment::METHOD_MOBILE_MONEY)
        ->and($payment->mobile_money_provider)->toBe(Payment::PROVIDER_MPESA)
        ->and($payment->transaction_id)->toBe('QJK7X2PLM9')
        ->and($payment->status)->toBe(Payment::STATUS_COMPLETED)
        ->and($payment->received_by_user_id)->toBe($this->owner->id)
        ->and($payment->receipt)->not->toBeNull()
        // Part-paid and past due: still overdue until the balance is cleared.
        ->and($invoice->fresh()?->status)->toBe(Invoice::STATUS_OVERDUE);
});

it('records a cash payment from the Today overdue list with the balance prefilled', function () {
    $invoice = payInvoice($this->client);
    payAs($this->owner, $this->client);

    Livewire::test(NeedsAttentionWidget::class)
        ->callAction(TestAction::make('recordPayment')->arguments(['invoice' => $invoice->id]))
        ->assertHasNoActionErrors()
        ->assertNotified('Payment recorded');

    expect($invoice->fresh()?->status)->toBe(Invoice::STATUS_PAID)
        ->and(Payment::query()->where('invoice_id', $invoice->id)->value('method'))->toBe(Payment::METHOD_CASH);
});

it('lets the Today header pick any open invoice, but only from this workspace', function () {
    $mine = payInvoice($this->client);
    $theirs = payInvoice($this->other, 'Someone Else');
    payAs($this->owner, $this->client);

    Livewire::test(Dashboard::class)
        ->callAction('recordPayment', ['invoice_id' => $theirs->id, 'amount' => 1000, 'channel' => Payment::METHOD_CASH, 'payment_date' => today()->toDateString()])
        ->assertHasActionErrors(['invoice_id']);

    Livewire::test(Dashboard::class)
        ->callAction('recordPayment', ['invoice_id' => $mine->id, 'amount' => 1000, 'channel' => Payment::PROVIDER_TIGOPESA, 'payment_date' => today()->toDateString()])
        ->assertHasNoActionErrors();

    Tenancy::end();
    expect(Payment::withoutGlobalScopes()->where('invoice_id', $theirs->id)->exists())->toBeFalse()
        ->and(Payment::withoutGlobalScopes()->where('invoice_id', $mine->id)->value('mobile_money_provider'))->toBe(Payment::PROVIDER_TIGOPESA);
});

it('keeps the modal open for the next payment with "Save & record another"', function () {
    $invoice = payInvoice($this->client);
    payAs($this->owner, $this->client);

    Livewire::test(ListInvoices::class)
        ->mountAction(TestAction::make('recordPayment')->table($invoice))
        ->setActionData(['amount' => 100_000])
        ->callMountedAction(['another' => true])
        ->assertHasNoActionErrors()
        ->assertActionMounted(TestAction::make('recordPayment')->table($invoice))
        // Same invoice, new balance.
        ->assertSchemaStateSet(['amount' => 400_000], 'mountedActionSchema0');
});

it('rejects payments dated in the future', function () {
    $invoice = payInvoice($this->client);
    payAs($this->owner, $this->client);

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('recordPayment')->table($invoice), ['payment_date' => today()->addDay()->toDateString()])
        ->assertHasActionErrors(['payment_date']);

    expect(Payment::query()->where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

it('offers Record payment on a renter page only for that renter\'s invoices', function () {
    $invoice = payInvoice($this->client, 'Hassan Omar');
    $other = payInvoice($this->client, 'Neema Mushi');
    payAs($this->owner, $this->client);
    $renter = $invoice->lease?->renter;

    Livewire::test(EditRenter::class, ['record' => $renter?->getKey()])
        ->assertActionVisible('recordPayment')
        // Their only open invoice is chosen, with its balance.
        ->mountAction('recordPayment')
        ->assertSchemaStateSet(['invoice_id' => $invoice->id, 'amount' => 500_000], 'mountedActionSchema0')
        ->unmountAction()
        ->callAction('recordPayment', ['invoice_id' => $other->id, 'amount' => 1000, 'channel' => Payment::METHOD_CASH, 'payment_date' => today()->toDateString()])
        ->assertHasActionErrors(['invoice_id']);
});

it('hides Record payment from roles without billing rights', function () {
    $invoice = payInvoice($this->client);

    $staff = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Fundi',
        'email' => 'fundi@pay.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $staff->assignRole('maintenance-staff');
    payAs($staff, $this->client);

    Livewire::test(Dashboard::class)->assertActionHidden('recordPayment');
});

it('serves the receipt PDF to operators of the same workspace only', function () {
    Storage::fake(config('filesystems.default'));
    $invoice = payInvoice($this->client);

    tenancy()->initialize($this->client);
    $payment = Payment::create(['invoice_id' => $invoice->id, 'amount' => 1000_00, 'currency' => 'TZS', 'payment_date' => today(), 'method' => Payment::METHOD_CASH, 'status' => Payment::STATUS_COMPLETED]);
    $receipt = $payment->receipt;
    expect($receipt)->not->toBeNull();
    // Pretend the PDF was rendered before, so no headless Chrome is needed.
    $receipt?->forceFill(['pdf_path' => 'receipts/test.pdf'])->save();
    Storage::disk(config('filesystems.default'))->put('receipts/test.pdf', '%PDF-1.4 test');
    Tenancy::end();

    $this->actingAs($this->owner, 'web')
        ->get('/manage/receipts/'.$receipt?->getKey().'/pdf')
        ->assertOk()
        ->assertDownload('receipt-'.$receipt?->receipt_number.'.pdf');

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->other->id);
    $outsider = User::create([
        'tenant_id' => $this->other->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Outsider',
        'email' => 'out@pay.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $outsider->assignRole('owner');
    $outsider->forceFill(['onboarding_completed_at' => now()])->save();

    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($outsider, 'web')
        ->get('/manage/receipts/'.$receipt?->getKey().'/pdf')
        ->assertNotFound();
});
