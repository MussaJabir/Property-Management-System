<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Filament\Operator\Resources\Leases\Pages\EditLease;
use App\Filament\Operator\Resources\Leases\Pages\ViewLease;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\LeaseEndingNotification;
use App\Services\RentInvoiceGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-01 09:00'));
    $this->client = Client::create(['slug' => 'termsco', 'name' => 'Terms Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@terms.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $this->unit = Unit::create(['property_id' => $property->id, 'code' => 'A3', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $this->renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Jean Uwase', 'phone' => '+255712000111']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function termsLease(array $attrs = []): Lease
{
    return Lease::create(array_merge([
        'unit_id' => test()->unit->id, 'renter_id' => test()->renter->id, 'status' => Lease::STATUS_PENDING,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'rent_amount' => 300_000_00, 'deposit_amount' => 600_000_00,
        'currency' => 'TZS', 'billing_cycle' => Lease::BILLING_MONTHLY, 'payment_due_day' => 5,
    ], $attrs));
}

function asLeaseOwner(): void
{
    test()->actingAs(test()->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
}

it('never lets a unit to two tenants for the same dates', function () {
    termsLease()->activate();

    $second = termsLease(['start_date' => '2026-06-01', 'end_date' => '2027-05-31']);
    expect(fn () => $second->activate())->toThrow(RuntimeException::class, 'A3 is already let to Jean Uwase until 31/12/2026');

    // Starting after the current lease ends is fine.
    $after = termsLease(['start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
    $after->activate();
    expect($after->fresh()->isActive())->toBeTrue();
});

it('keeps the unit occupied when a lease ends and its renewal is running', function () {
    $lease = termsLease();
    $lease->activate();
    $renewal = $lease->renew(['start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'rent_amount' => 315_000_00, 'deposit_amount' => 600_000_00, 'carry_deposit' => true, 'activate' => true]);

    $lease->end();
    expect($this->unit->fresh()->status)->toBe(Unit::STATUS_OCCUPIED);

    $renewal->terminate('Moved out');
    expect($this->unit->fresh()->status)->toBe(Unit::STATUS_VACANT);
});

it('waits for the grace period before an invoice is overdue, then adds the late fee once', function () {
    $lease = termsLease(['grace_days' => 5, 'late_fee_type' => Lease::LATE_FEE_FIXED, 'late_fee_value' => 20_000_00, 'deposit_amount' => 0]);
    $lease->activate();
    $invoice = Invoice::query()->where('lease_id', $lease->id)->sole();
    expect($invoice->due_date->toDateString())->toBe('2026-10-05');

    $this->travelTo(Carbon::parse('2026-10-10 09:00'));
    expect($invoice->fresh()->markOverdueIfDue())->toBeFalse();

    $this->travelTo(Carbon::parse('2026-10-11 09:00'));
    expect($invoice->fresh()->markOverdueIfDue())->toBeTrue();

    $invoice = $invoice->fresh();
    expect($invoice->status)->toBe(Invoice::STATUS_OVERDUE)
        ->and($invoice->total_amount)->toBe(320_000_00)
        ->and($invoice->items()->where('type', InvoiceItem::TYPE_LATE_FEE)->count())->toBe(1);

    $invoice->addLateFee();
    expect($invoice->items()->where('type', InvoiceItem::TYPE_LATE_FEE)->count())->toBe(1);
});

it('works out a percentage late fee to a whole amount', function () {
    $lease = termsLease(['late_fee_type' => Lease::LATE_FEE_PERCENT, 'late_fee_value' => 250]);

    expect($lease->lateFeeFor(300_000_00))->toBe(7_500_00)
        ->and(termsLease()->lateFeeFor(300_000_00))->toBe(0);
});

it('raises the rent on the increase date and records it', function () {
    $lease = termsLease(['start_date' => '2025-10-01', 'end_date' => null, 'increase_percent' => 5, 'increase_every_months' => 12, 'deposit_amount' => 0]);
    $lease->activate();

    $invoice = Invoice::query()->where('lease_id', $lease->id)->sole();
    expect($invoice->total_amount)->toBe(315_000_00)
        ->and($lease->fresh()->rent_amount)->toBe(315_000_00)
        ->and($lease->fresh()->nextIncreaseOn()?->toDateString())->toBe('2027-10-01')
        ->and($lease->history()->where('action', 'rent_increased')->count())->toBe(1);

    // Not again for the next month.
    $this->travelTo(Carbon::parse('2026-11-02 09:00'));
    app(RentInvoiceGenerator::class)->generateFor($lease->fresh());
    expect($lease->fresh()->rent_amount)->toBe(315_000_00);
});

it('renews from the lease page with the increase and the deposit carried over', function () {
    $lease = termsLease(['increase_percent' => 5, 'increase_every_months' => 12]);
    $lease->activate();
    asLeaseOwner();

    $this->get(LeaseResource::getUrl('view', ['record' => $lease]))->assertOk()->assertSee('Terms');

    Livewire::test(ViewLease::class, ['record' => $lease->getKey()])
        ->assertSee('Rent increase')
        ->mountAction('renew')
        ->assertActionDataSet(['start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'monthly_rent' => 315000, 'carry_deposit' => true])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $renewal = $lease->fresh()->renewal;
    expect($renewal)->not->toBeNull()
        ->and($renewal->isActive())->toBeTrue()
        ->and($renewal->rent_amount)->toBe(315_000_00)
        ->and($renewal->deposit_carried_over)->toBe(600_000_00);

    // On its first day it bills rent only: the deposit was carried over.
    $this->travelTo(Carbon::parse('2027-01-01 09:00'));
    $first = app(RentInvoiceGenerator::class)->generateFor($renewal->fresh());
    expect($first?->items->pluck('type')->all())->toBe([InvoiceItem::TYPE_RENT]);
});

it('saves the new terms from the edit form', function () {
    $lease = termsLease();
    asLeaseOwner();

    Livewire::test(EditLease::class, ['record' => $lease->getKey()])
        ->fillForm(['grace_days' => 7, 'notice_days' => 30, 'late_fee_type' => Lease::LATE_FEE_PERCENT, 'late_fee_value' => 2.5, 'increase_percent' => 5, 'increase_every_months' => 12])
        ->call('save')
        ->assertHasNoFormErrors();

    $lease = $lease->fresh();
    expect($lease->grace_days)->toBe(7)
        ->and($lease->notice_days)->toBe(30)
        ->and($lease->late_fee_value)->toBe(250)
        ->and((float) $lease->increase_percent)->toBe(5.0);
});

it('reminds staff at 90, 60 and 30 days and the renter at 60, skipping renewed leases', function () {
    Notification::fake();
    $renterUser = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_RENTER, 'name' => 'Jean',
        'email' => 'jean@terms.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $lease = termsLease(['end_date' => today()->addDays(60)->toDateString()]);
    $lease->activate();
    // Starting a lease opens a portal account; use ours for the renter.
    $this->renter->forceFill(['user_id' => $renterUser->id])->save();
    Tenancy::end();

    $this->artisan('leases:remind-ending')->assertSuccessful();

    Notification::assertSentTo($this->owner, LeaseEndingNotification::class, fn (LeaseEndingNotification $n): bool => $n->daysLeft === 60 && ! $n->forRenter);
    Notification::assertSentTo($renterUser, LeaseEndingNotification::class, fn (LeaseEndingNotification $n): bool => $n->forRenter);

    // Not on other days.
    Notification::fake();
    $this->travelTo(now()->addDay());
    $this->artisan('leases:remind-ending')->assertSuccessful();
    Notification::assertNothingSent();

    // Not once renewed.
    tenancy()->initialize($this->client);
    $lease->fresh()->renew(['start_date' => $lease->end_date->copy()->addDay()->toDateString(), 'end_date' => null, 'rent_amount' => 300_000_00, 'deposit_amount' => 0, 'carry_deposit' => false, 'activate' => false]);
    Tenancy::end();
    $this->travelTo(now()->addDays(29));
    $this->artisan('leases:remind-ending')->assertSuccessful();
    Notification::assertNothingSent();
});

it('ends the old lease once its renewal has started', function () {
    $lease = termsLease(['end_date' => '2026-10-31']);
    $lease->activate();
    $renewal = $lease->renew(['start_date' => '2026-11-01', 'end_date' => '2027-10-31', 'rent_amount' => 300_000_00, 'deposit_amount' => 600_000_00, 'carry_deposit' => true, 'activate' => true]);
    Tenancy::end();

    $this->travelTo(Carbon::parse('2026-11-01 09:00'));
    $this->artisan('leases:remind-ending')->assertSuccessful();

    tenancy()->initialize($this->client);
    expect($lease->fresh()->status)->toBe(Lease::STATUS_ENDED)
        ->and($renewal->fresh()->isActive())->toBeTrue()
        ->and($this->unit->fresh()->status)->toBe(Unit::STATUS_OCCUPIED);
});
