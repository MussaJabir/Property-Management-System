<?php

declare(strict_types=1);

use App\Exceptions\PlanLimitReached;
use App\Filament\Admin\Resources\Clients\Pages\ListClients;
use App\Filament\Operator\Pages\Reports\OccupancyPage;
use App\Filament\Operator\Pages\RolePermissions;
use App\Filament\Operator\Resources\Units\Pages\ListUnits;
use App\Models\Client;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\SuperAdminUser;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PlanUpgradeRequestedNotification;
use App\Services\Plans\PlanUsage;
use App\Services\Portal\RenterPortalAccountProvisioner;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->plan = Plan::create([
        'name' => 'Tiny',
        'slug' => 'tiny',
        'price_tzs' => 1_000_000,
        'billing_period' => 'monthly',
        'max_properties' => 1,
        'max_units' => 2,
        'max_operators' => 2,
        'features' => [],
    ]);
    $this->client = Client::create(['slug' => 'limitco', 'name' => 'Limit Co.', 'status' => 'active', 'plan_id' => $this->plan->id]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    RateLimiter::clear('plan-upgrade-request:limitco');
});

function limitOwner(): User
{
    /** @var Client $client */
    $client = test()->client;
    app(PermissionRegistrar::class)->setPermissionsTeamId($client->getKey());

    $owner = User::create([
        'tenant_id' => $client->getKey(),
        'type' => User::TYPE_OPERATOR,
        'name' => 'Owner',
        'email' => 'owner@limitco.test',
        'password' => 'secret-password',
        'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');

    return $owner;
}

/**
 * Run inside the client's workspace: properties and units take their
 * tenant_id from the active tenancy, never from mass assignment.
 */
function inClient(Client $client, Closure $callback): mixed
{
    $previous = tenancy()->tenant;
    tenancy()->initialize($client);

    try {
        return $callback();
    } finally {
        $previous ? tenancy()->initialize($previous) : tenancy()->end();
    }
}

function limitProperty(Client $client, string $name = 'Block A'): Property
{
    return inClient($client, function () use ($name): Property {
        $location = Location::firstOrCreate(['name' => 'LLoc'], ['region' => 'Dar', 'district' => 'Ilala']);

        return Property::create(['location_id' => $location->id, 'name' => $name, 'status' => 'active']);
    });
}

function limitUnit(Property $property, string $code): Unit
{
    return inClient(Client::findOrFail($property->tenant_id), fn (): Unit => Unit::create([
        'property_id' => $property->id,
        'code' => $code,
        'status' => Unit::STATUS_VACANT,
        'rent_amount' => 100_00,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
    ]));
}

it('counts only this client\'s live records', function () {
    $other = Client::create(['slug' => 'otherco', 'name' => 'Other Co.', 'status' => 'active']);
    limitUnit(limitProperty($other, 'Other'), 'O1');

    $property = limitProperty($this->client);
    limitUnit($property, 'A1');
    limitUnit($property, 'A2')->delete();

    $usage = PlanUsage::for($this->client);

    expect($usage->used(PlanUsage::PROPERTIES))->toBe(1)
        ->and($usage->used(PlanUsage::UNITS))->toBe(1)
        ->and($usage->limit(PlanUsage::UNITS))->toBe(2);
});

it('treats a client without a plan as unlimited with every feature', function () {
    $free = Client::create(['slug' => 'freeco', 'name' => 'Free Co.', 'status' => 'active']);
    $usage = PlanUsage::for($free);

    expect($usage->limit(PlanUsage::UNITS))->toBeNull()
        ->and($usage->canAdd(PlanUsage::UNITS, 1000))->toBeTrue()
        ->and($usage->hasFeature('reports'))->toBeTrue();
});

it('refuses to create a unit past the plan limit, whatever the path', function () {
    $property = limitProperty($this->client);
    limitUnit($property, 'A1');
    limitUnit($property, 'A2');

    expect(fn () => limitUnit($property, 'A3'))->toThrow(PlanLimitReached::class);
    expect(PlanUsage::for($this->client)->used(PlanUsage::UNITS))->toBe(2);
});

it('refuses a second property and a third staff account on this plan', function () {
    limitProperty($this->client);
    expect(fn () => limitProperty($this->client, 'Block B'))->toThrow(PlanLimitReached::class);

    limitOwner();
    User::create(['tenant_id' => 'limitco', 'type' => User::TYPE_OPERATOR, 'name' => 'Two', 'email' => 'two@limitco.test', 'password' => 'x', 'status' => User::STATUS_ACTIVE]);
    expect(fn () => User::create(['tenant_id' => 'limitco', 'type' => User::TYPE_OPERATOR, 'name' => 'Three', 'email' => 'three@limitco.test', 'password' => 'x', 'status' => User::STATUS_ACTIVE]))
        ->toThrow(PlanLimitReached::class);
});

it('does not count disabled staff toward the limit', function () {
    limitOwner();
    User::create(['tenant_id' => 'limitco', 'type' => User::TYPE_OPERATOR, 'name' => 'Gone', 'email' => 'gone@limitco.test', 'password' => 'x', 'status' => User::STATUS_DISABLED]);

    expect(PlanUsage::for($this->client)->used(PlanUsage::STAFF))->toBe(1);
});

it('lets a client past the limit while an admin allowance is active', function () {
    $this->client->update(['limits_waived_until' => now()->addDays(7)]);
    $property = limitProperty($this->client);
    limitUnit($property, 'A1');
    limitUnit($property, 'A2');

    expect(limitUnit($property, 'A3')->exists)->toBeTrue();

    $this->client->update(['limits_waived_until' => now()->subDay()]);
    expect(fn () => limitUnit($property, 'A4'))->toThrow(PlanLimitReached::class);
});

it('hides "new unit" at the limit and shows the usage and upgrade request', function () {
    $owner = limitOwner();
    $property = limitProperty($this->client);
    limitUnit($property, 'A1');

    $this->actingAs($owner, 'web')->get('/manage/units')
        ->assertOk()
        ->assertSee('1 of 2 units used on the Tiny plan.')
        ->assertSee('/manage/units/create', false)
        ->assertDontSee('Request an upgrade');
    $this->get('/manage/units/create')->assertOk();

    limitUnit($property, 'A2');

    $this->get('/manage/units')
        ->assertOk()
        ->assertSee('You have reached your plan limit.')
        ->assertSee('Request an upgrade')
        ->assertDontSee('/manage/units/create', false);
    $this->get('/manage/units/create')->assertForbidden();
});

it('emails the BJP team once when a client requests an upgrade', function () {
    Notification::fake();
    $admin = SuperAdminUser::create(['name' => 'BJP', 'email' => 'admin@pms.local', 'password' => 'password']);
    $owner = limitOwner();
    $property = limitProperty($this->client);
    limitUnit($property, 'A1');
    limitUnit($property, 'A2');

    $this->actingAs($owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    Livewire::test(ListUnits::class)
        ->callAction('requestUpgrade')
        ->assertNotified('Upgrade request sent');
    Livewire::test(ListUnits::class)
        ->callAction('requestUpgrade')
        ->assertNotified('Request already sent');

    Notification::assertSentToTimes($admin, PlanUpgradeRequestedNotification::class, 1);
});

it('switches off reports and custom roles when the plan does not include them', function () {
    $owner = limitOwner();
    $this->actingAs($owner, 'web');

    expect(OccupancyPage::canAccess())->toBeFalse()
        ->and(RolePermissions::canAccess())->toBeFalse();

    $this->plan->update(['features' => ['reports', 'custom_roles']]);
    $owner->refresh();

    expect(OccupancyPage::canAccess())->toBeTrue()
        ->and(RolePermissions::canAccess())->toBeTrue();
});

it('closes the renter portal and skips portal accounts without the feature', function () {
    $this->get('/limitco/portal/login')->assertNotFound();

    $renter = inClient($this->client, fn (): Renter => Renter::create([
        'type' => Renter::TYPE_INDIVIDUAL,
        'full_name' => 'No Portal',
        'phone' => '+255712009111',
    ]));
    expect(app(RenterPortalAccountProvisioner::class)->provisionFor($renter))->toBeNull();

    $this->plan->update(['features' => ['renter_portal']]);
    Tenancy::end();
    $this->get('/limitco/portal/login')->assertOk();
});

it('shows unit usage to the super admin and filters clients near their limit', function () {
    $property = limitProperty($this->client);
    limitUnit($property, 'A1');
    limitUnit($property, 'A2');
    $roomy = Client::create(['slug' => 'roomyco', 'name' => 'Roomy Co.', 'status' => 'active', 'plan_id' => $this->plan->id]);

    $this->actingAs(SuperAdminUser::create(['name' => 'BJP', 'email' => 'admin@pms.local', 'password' => 'password']), 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListClients::class)
        ->assertTableColumnStateSet('unit_usage', '2 / 2', $this->client)
        ->filterTable('near_unit_limit')
        ->assertCanSeeTableRecords([$this->client])
        ->assertCanNotSeeTableRecords([$roomy]);
});

it('lets the super admin pause and resume a client\'s limits', function () {
    $this->actingAs(SuperAdminUser::create(['name' => 'BJP', 'email' => 'admin@pms.local', 'password' => 'password']), 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListClients::class)
        ->callTableAction('allowOverLimit', $this->client, data: ['days' => 14]);

    expect($this->client->fresh()->limits_waived_until?->isFuture())->toBeTrue();

    Livewire::test(ListClients::class)
        ->callTableAction('endAllowance', $this->client);

    expect($this->client->fresh()->limits_waived_until)->toBeNull();
});
