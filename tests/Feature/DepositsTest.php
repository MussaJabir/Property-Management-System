<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Leases\Pages\ViewLease;
use App\Models\Client;
use App\Models\DepositTransaction;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Services\Deposits\DepositLedger;
use App\Services\Payments\PaymentAllocator;
use App\Services\Payments\TenantStatement;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-01-01 09:00'));
    $this->client = Client::create(['slug' => 'depco', 'name' => 'Deposit Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);

    $this->owner = depositUser('owner', 'asha');
    tenancy()->initialize($this->client);

    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'A3', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $this->renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Jean Uwase', 'phone' => '+255712000111']);
    $this->lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $this->renter->id, 'status' => Lease::STATUS_PENDING,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_amount' => 300_000_00, 'deposit_amount' => 600_000_00,
        'currency' => 'TZS', 'billing_cycle' => Lease::BILLING_MONTHLY, 'payment_due_day' => 5,
    ]);
    // Activation bills the first month with the deposit: 900,000.
    $this->lease->activate();
    $this->first = Invoice::query()->where('lease_id', $this->lease->id)->sole();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function depositUser(string $role, string $name): User
{
    $user = User::create([
        'tenant_id' => test()->client->id, 'type' => User::TYPE_OPERATOR, 'name' => ucfirst($name),
        'email' => $name.'@dep.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $user->assignRole($role);
    $user->forceFill(['onboarding_completed_at' => now()])->save();

    return $user;
}

function payDeposit(int $amount): Payment
{
    return Payment::create([
        'invoice_id' => test()->first->id, 'amount' => $amount, 'currency' => 'TZS',
        'payment_date' => today()->toDateString(), 'method' => Payment::METHOD_CASH, 'status' => Payment::STATUS_COMPLETED,
    ]);
}

it('holds the deposit once its invoice is fully paid, and lets go if the payment is reversed', function () {
    $ledger = app(DepositLedger::class);
    expect($this->first->total_amount)->toBe(900_000_00);

    $part = payDeposit(400_000_00);
    expect($ledger->held($this->lease))->toBe(0);

    payDeposit(500_000_00);
    expect($ledger->held($this->lease))->toBe(600_000_00)
        ->and($this->lease->depositTransactions()->sole()->type)->toBe(DepositTransaction::TYPE_RECEIVED);

    app(PaymentAllocator::class)->reverse($part, 'Recorded twice');
    expect($ledger->held($this->lease))->toBe(0);
});

it('deducts with a reason and never more than is held', function () {
    payDeposit(900_000_00);
    $ledger = app(DepositLedger::class);

    expect(fn () => $ledger->deduct($this->lease, 50_000_00, ''))->toThrow(RuntimeException::class)
        ->and(fn () => $ledger->deduct($this->lease, 700_000_00, 'Damage'))->toThrow(RuntimeException::class);

    $ledger->deduct($this->lease, 80_000_00, 'Broken window');
    $ledger->refund($this->lease, 520_000_00, 'Paid by M-Pesa');

    expect($ledger->held($this->lease))->toBe(0);
});

it('lets staff deduct on the lease page and only owners or managers refund', function () {
    payDeposit(900_000_00);
    $accountant = depositUser('accountant', 'neema');

    $this->actingAs($accountant, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    Livewire::test(ViewLease::class, ['record' => $this->lease->getKey()])
        ->assertSee('Held now')
        ->assertActionHidden('refundDeposit');

    $this->actingAs($this->owner, 'web');
    Livewire::test(ViewLease::class, ['record' => $this->lease->getKey()])
        ->callAction('deductDeposit', ['amount' => 80_000, 'reason' => 'Broken window'])
        ->assertHasNoActionErrors()
        ->assertNotified('Deduction recorded');

    Livewire::test(ViewLease::class, ['record' => $this->lease->getKey()])
        ->assertSee('Broken window')
        ->mountAction('refundDeposit')
        ->assertActionDataSet(['amount' => 520_000])
        ->callMountedAction()
        ->assertNotified('Refund recorded');

    expect(app(DepositLedger::class)->held($this->lease))->toBe(0);
});

it('carries the deposit over to the renewal so it is not billed again', function () {
    payDeposit(900_000_00);

    $renewal = $this->lease->renew(['start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'rent_amount' => 315_000_00, 'deposit_amount' => 600_000_00, 'carry_deposit' => true, 'activate' => true]);

    $ledger = app(DepositLedger::class);
    expect($ledger->held($this->lease))->toBe(0)
        ->and($ledger->held($renewal))->toBe(600_000_00)
        ->and($ledger->heldForRenter($this->renter->id))->toBe(600_000_00);
});

it('shows the deposit on the statement, apart from what is owed', function () {
    payDeposit(900_000_00);

    $statement = app(TenantStatement::class)->build($this->renter, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($statement['closing'])->toBe(0)
        ->and($statement['deposit_held'])->toBe(600_000_00);
});

it('records a deposit paid before the workspace used PMS', function () {
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ViewLease::class, ['record' => $this->lease->getKey()])
        ->mountAction('receiveDeposit')
        ->assertActionDataSet(['amount' => 600_000, 'date' => '2026-01-01'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Deposit recorded')
        ->assertActionHidden('receiveDeposit');

    expect(app(DepositLedger::class)->held($this->lease))->toBe(600_000_00);
});
