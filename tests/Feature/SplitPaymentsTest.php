<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\Dashboard;
use App\Filament\Operator\Resources\Payments\Pages\ListPayments;
use App\Filament\Operator\Resources\Renters\Pages\RenterStatement;
use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Services\Payments\PaymentAllocator;
use App\Services\Payments\TenantStatement;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-24 09:00'));
    $this->client = Client::create(['slug' => 'splitco', 'name' => 'Split Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@split.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'A3', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $this->renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Jean Uwase', 'phone' => '+255712000111']);
    $this->lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $this->renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-09-01', 'rent_amount' => 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => Lease::BILLING_MONTHLY, 'payment_due_day' => 1,
    ]);

    // September 300,000 (part paid later), October rent 300,000 and water 20,000.
    $this->sep = splitInvoice($this->lease, 300_000_00, '2026-09-01', 'Rent, September');
    $this->oct = splitInvoice($this->lease, 300_000_00, '2026-10-01', 'Rent, October');
    $this->water = splitInvoice($this->lease, 20_000_00, '2026-10-02', 'Water, October');
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function splitInvoice(Lease $lease, int $amount, string $date, string $what): Invoice
{
    Carbon::setTestNow(Carbon::parse($date.' 08:00'));
    $invoice = Invoice::create([
        'lease_id' => $lease->id, 'status' => Invoice::STATUS_DRAFT, 'currency' => 'TZS',
        'billing_period_start' => $date, 'billing_period_end' => Carbon::parse($date)->endOfMonth()->toDateString(), 'due_date' => $date,
    ]);
    $invoice->items()->create(['description' => $what, 'type' => 'rent', 'quantity' => 1, 'unit_price' => $amount]);
    $invoice->refresh()->issue();
    Carbon::setTestNow(Carbon::parse('2026-11-24 09:00'));

    return $invoice->fresh();
}

function cashPayment(Renter $renter, int $amount, ?Invoice $invoice = null, string $date = '2026-11-24'): Payment
{
    return Payment::create([
        'invoice_id' => $invoice?->id, 'renter_id' => $renter->id, 'amount' => $amount, 'currency' => 'TZS',
        'payment_date' => $date, 'method' => Payment::METHOD_CASH, 'status' => Payment::STATUS_COMPLETED,
    ]);
}

it('pays the oldest invoices first and keeps what is left as credit', function () {
    cashPayment($this->renter, 200_000_00, $this->sep, '2026-09-05');
    $payment = cashPayment($this->renter, 450_000_00);

    expect($this->sep->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($this->oct->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($this->water->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($payment->allocations()->pluck('amount', 'invoice_id')->all())->toBe([
            $this->sep->id => 100_000_00, $this->oct->id => 300_000_00, $this->water->id => 20_000_00,
        ])
        ->and($payment->fresh()->unappliedAmount())->toBe(30_000_00)
        ->and(app(PaymentAllocator::class)->creditFor($this->renter->id))->toBe(30_000_00);

    // The next invoice uses the credit by itself.
    $nov = splitInvoice($this->lease, 300_000_00, '2026-11-25', 'Rent, November');
    expect($nov->fresh()->amount_paid)->toBe(30_000_00)
        ->and($nov->fresh()->status)->toBe(Invoice::STATUS_PARTIAL)
        ->and(app(PaymentAllocator::class)->creditFor($this->renter->id))->toBe(0);
});

it('shares a payment the way staff set it in Record payment', function () {
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(Dashboard::class)
        ->mountAction('recordPayment')
        ->fillForm(['payer' => 'renter:'.$this->renter->id])
        ->assertSchemaStateSet(['amount' => 620_000], 'mountedActionSchema0')
        ->fillForm(['amount' => 350_000])
        ->fillForm(['split' => [
            ['invoice_id' => $this->sep->id, 'owed' => 300000, 'pay' => 0, 'label' => 'Sep'],
            ['invoice_id' => $this->oct->id, 'owed' => 300000, 'pay' => 300000, 'label' => 'Oct'],
            ['invoice_id' => $this->water->id, 'owed' => 20000, 'pay' => 20000, 'label' => 'Water'],
        ], 'channel' => Payment::METHOD_CASH])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Payment recorded');

    $payment = Payment::query()->sole();
    expect($this->sep->fresh()->status)->not->toBe(Invoice::STATUS_PAID)
        ->and($this->oct->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($this->water->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($payment->unappliedAmount())->toBe(30_000_00)
        ->and($payment->receipt)->not->toBeNull();
});

it('refuses shares that add up to more than the money received', function () {
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(Dashboard::class)
        ->mountAction('recordPayment')
        ->fillForm(['payer' => 'renter:'.$this->renter->id, 'amount' => 100_000, 'channel' => Payment::METHOD_CASH])
        ->fillForm(['split' => [
            ['invoice_id' => $this->sep->id, 'owed' => 300000, 'pay' => 300000, 'label' => 'Sep'],
        ]])
        ->callMountedAction()
        ->assertNotified('The shares add up to more than the amount received');

    expect(Payment::query()->exists())->toBeFalse();
});

it('reverses a payment with a reason instead of deleting it', function () {
    $payment = cashPayment($this->renter, 300_000_00, $this->sep);
    expect($this->sep->fresh()->status)->toBe(Invoice::STATUS_PAID);

    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ListPayments::class)
        ->assertActionHidden(TestAction::make('edit')->table($payment))
        ->callAction(TestAction::make('reverse')->table($payment), ['reason' => ''])
        ->assertHasActionErrors(['reason']);

    Livewire::test(ListPayments::class)
        ->callAction(TestAction::make('reverse')->table($payment), ['reason' => 'Cheque bounced'])
        ->assertHasNoActionErrors()
        ->assertNotified('Payment reversed');

    $payment = $payment->fresh();
    expect($payment->status)->toBe(Payment::STATUS_REVERSED)
        ->and($payment->reversal_reason)->toBe('Cheque bounced')
        ->and($payment->reversed_by_user_id)->toBe($this->owner->id)
        ->and($payment->receipt)->not->toBeNull()
        ->and($this->sep->fresh()->status)->not->toBe(Invoice::STATUS_PAID)
        ->and($this->owner->can('delete', $payment))->toBeFalse()
        ->and($this->owner->can('reverse', $payment))->toBeFalse();
});

it('builds the statement with a running balance, reversals and credit', function () {
    cashPayment($this->renter, 200_000_00, $this->sep, '2026-09-05');
    $cheque = cashPayment($this->renter, 100_000_00, null, '2026-10-12');
    $this->travelTo(Carbon::parse('2026-10-15 10:00'));
    app(PaymentAllocator::class)->reverse($cheque, 'Cheque bounced', $this->owner->id);
    $this->travelTo(Carbon::parse('2026-11-24 09:00'));
    cashPayment($this->renter, 450_000_00);

    $statement = app(TenantStatement::class)->build($this->renter, Carbon::parse('2026-09-01'), Carbon::parse('2026-11-24'));

    expect($statement['opening'])->toBe(0)
        ->and($statement['charged'])->toBe(620_000_00)
        ->and($statement['paid'])->toBe(650_000_00)
        ->and($statement['closing'])->toBe(-30_000_00)
        ->and(collect($statement['rows'])->pluck('balance')->all())->toBe([
            300_000_00, 100_000_00, 400_000_00, 420_000_00, 320_000_00, 420_000_00, -30_000_00,
        ])
        ->and(collect($statement['rows'])->firstWhere('reversal', true)['what'])->toBe('Payment reversed: Cheque bounced');

    // Later dates start from the balance carried in.
    expect(app(TenantStatement::class)->build($this->renter, Carbon::parse('2026-10-13'), Carbon::parse('2026-11-24'))['opening'])->toBe(320_000_00);
});

it('shows the statement to staff and to the renter, each only their own', function () {
    cashPayment($this->renter, 450_000_00);

    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    Livewire::test(RenterStatement::class, ['record' => $this->renter->getKey()])
        ->assertOk()
        ->assertSee('Opening balance')
        ->assertSee('Rent, October');

    Tenancy::end();
    $this->get(RenterResource::getUrl('statement', ['record' => $this->renter], panel: 'operator'))->assertOk();

    $renterUser = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_RENTER, 'name' => 'Jean',
        'email' => 'jean@split.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->renter->forceFill(['user_id' => $renterUser->id])->save();

    $this->actingAs($renterUser, 'renter')
        ->get('/splitco/portal/statement')
        ->assertOk()
        ->assertSee('Rent, September')
        ->assertSee('170,000');
});
