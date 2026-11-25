<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\ActivityLog;
use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Models\Activity;
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
use App\Support\TwoStepLogin;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'auditco', 'name' => 'Audit Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = auditUser('owner', 'asha');

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'A3', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Jean Uwase', 'phone' => '+255712000111']);
    $lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-01-01', 'rent_amount' => 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 5,
    ]);
    $this->invoice = Invoice::create(['lease_id' => $lease->id, 'status' => Invoice::STATUS_DRAFT, 'currency' => 'TZS', 'billing_period_start' => '2026-11-01', 'billing_period_end' => '2026-11-30', 'due_date' => '2026-11-05']);
    $this->invoice->items()->create(['description' => 'Rent', 'type' => 'rent', 'quantity' => 1, 'unit_price' => 300_000_00]);
    $this->invoice->refresh()->issue();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function auditUser(string $role, string $name): User
{
    $user = User::create([
        'tenant_id' => test()->client->id, 'type' => User::TYPE_OPERATOR, 'name' => ucfirst($name),
        'email' => $name.'@audit.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $user->assignRole($role);
    $user->forceFill(['onboarding_completed_at' => now()])->save();

    return $user;
}

function signIn(User $user): void
{
    test()->actingAs($user, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
}

it('records who changed a payment, with the old and new values', function () {
    signIn($this->owner);
    $payment = Payment::create(['invoice_id' => $this->invoice->id, 'amount' => 300_000_00, 'currency' => 'TZS', 'payment_date' => today()->toDateString(), 'method' => Payment::METHOD_CASH, 'status' => Payment::STATUS_COMPLETED]);

    app(PaymentAllocator::class)->reverse($payment, 'Cheque bounced', $this->owner->id);

    $entry = Activity::query()->where('log_name', 'payments')->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->tenant_id)->toBe($this->client->id)
        ->and($entry->causer_id)->toBe($this->owner->id)
        ->and($entry->subject_id)->toBe($payment->id)
        ->and($entry->attribute_changes['old']['status'])->toBe(Payment::STATUS_COMPLETED)
        ->and($entry->attribute_changes['attributes']['status'])->toBe(Payment::STATUS_REVERSED);

    Livewire::test(ActivityLog::class)
        ->assertOk()
        ->assertSee('Changed payment')
        ->assertSee('completed → reversed')
        ->assertSee('Cheque bounced');
});

it('keeps each workspace\'s log to itself', function () {
    expect(Activity::query()->count())->toBeGreaterThan(0);

    Tenancy::end();
    $other = Client::create(['slug' => 'otheraudit', 'name' => 'Other Audit', 'status' => 'active']);
    tenancy()->initialize($other);

    expect(Activity::query()->count())->toBe(0);
});

it('opens the activity log to owners, managers and auditors only', function () {
    $auditor = auditUser('auditor', 'mutesi');
    $accountant = auditUser('accountant', 'neema');
    Tenancy::end();

    $this->actingAs($auditor, 'web')->get(ActivityLog::getUrl(panel: 'operator'))->assertOk();
    $this->flushSession();
    $this->actingAs($accountant, 'web')->get(ActivityLog::getUrl(panel: 'operator'))->assertForbidden();
});

it('lets an auditor read everything and change nothing', function () {
    $auditor = auditUser('auditor', 'mutesi');
    Tenancy::end();
    $this->actingAs($auditor, 'web');

    $this->get('/manage/leases')->assertOk();
    $this->get('/manage/invoices')->assertOk();
    $this->get('/manage/payments')->assertOk();
    $this->get('/manage/leases/create')->assertForbidden();
    $this->get('/manage/payments/create')->assertForbidden();
    $this->get('/manage/workspace-settings')->assertForbidden();
});

it('can require 2-step login for owners and managers', function () {
    $accountant = auditUser('accountant', 'neema');
    expect(TwoStepLogin::requiredFor($this->owner))->toBeFalse();

    signIn($this->owner);
    Livewire::test(WorkspaceSettings::class)
        ->set('data.'.TwoStepLogin::SETTING, true)
        ->call('save')
        ->assertHasNoErrors();

    expect(TwoStepLogin::requiredFor($this->owner->fresh()))->toBeTrue()
        ->and(TwoStepLogin::requiredFor($accountant))->toBeFalse();

    // Until they set it up, an owner is sent to set it up.
    Tenancy::end();
    $this->actingAs($this->owner->fresh(), 'web')
        ->get('/manage')
        ->assertRedirect(route('filament.operator.auth.multi-factor-authentication.set-up-required'));

    // Others carry on as before.
    $this->flushSession();
    $this->actingAs($accountant, 'web')->get('/manage')->assertOk();
});
