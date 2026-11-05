<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Clients\Pages\CreateClient;
use App\Filament\Operator\Resources\Units\Pages\EditUnit;
use App\Filament\Operator\Resources\Units\Pages\ListUnits;
use App\Models\Client;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SuperAdminUser;
use App\Models\Unit;
use App\Models\User;
use App\Support\ReservedSlugs;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

function marketplaceOperator(Client $client): void
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);
    $owner = User::create([
        'tenant_id' => $client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Neema',
        'email' => 'neema@'.$client->slug.'.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');
    $owner->forceFill(['onboarding_completed_at' => now()])->save();

    test()->actingAs($owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($client);
}

function marketplaceUnit(string $code): Unit
{
    $property = Property::query()->first() ?? Property::create([
        'location_id' => Location::create(['name' => 'Njiro', 'region' => 'Arusha', 'district' => 'Arusha City'])->id,
        'name' => 'Njiro Heights', 'status' => 'active',
    ]);

    return Unit::create([
        'property_id' => $property->id, 'code' => $code, 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 350_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
    ]);
}

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('includes the marketplace on every paid plan', function () {
    $this->seed(PlanSeeder::class);

    Plan::all()->each(fn (Plan $plan) => expect($plan->features)->toContain('marketplace_listing'));
});

it('keeps new units off the marketplace until the landlord switches them on', function () {
    marketplaceOperator(Client::create(['slug' => 'mkt-one', 'name' => 'Mkt One', 'status' => 'active']));

    expect(marketplaceUnit('R1')->fresh()->on_marketplace)->toBeFalse();
});

it('lets the landlord list a unit from its form', function () {
    marketplaceOperator(Client::create(['slug' => 'mkt-two', 'name' => 'Mkt Two', 'status' => 'active']));
    $unit = marketplaceUnit('R1');

    Livewire::test(EditUnit::class, ['record' => $unit->getRouteKey()])
        ->assertSee('Show this unit on the PMS marketplace')
        ->fillForm(['on_marketplace' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($unit->fresh()->on_marketplace)->toBeTrue();
});

it('lists and removes many units at once', function () {
    marketplaceOperator(Client::create(['slug' => 'mkt-three', 'name' => 'Mkt Three', 'status' => 'active']));
    $units = collect(['R1', 'R2', 'R3'])->map(fn (string $code) => marketplaceUnit($code));

    Livewire::test(ListUnits::class)
        ->callTableBulkAction('listOnMarketplace', $units->take(2))
        ->assertNotified();

    expect($units->map(fn (Unit $u) => $u->fresh()->on_marketplace)->all())->toBe([true, true, false]);

    Livewire::test(ListUnits::class)->callTableBulkAction('removeFromMarketplace', $units->take(1));

    expect($units->first()->fresh()->on_marketplace)->toBeFalse();
});

it('hides the marketplace switches when the plan does not include it', function () {
    $plan = Plan::create([
        'name' => 'Legacy', 'slug' => 'legacy', 'price_tzs' => 0, 'billing_period' => 'monthly',
        'features' => ['cms_website'], 'is_public' => false,
    ]);
    marketplaceOperator(Client::create(['slug' => 'mkt-four', 'name' => 'Mkt Four', 'status' => 'active', 'plan_id' => $plan->id]));
    $unit = marketplaceUnit('R1');

    Livewire::test(EditUnit::class, ['record' => $unit->getRouteKey()])
        ->assertDontSee('Show this unit on the PMS marketplace');

    Livewire::test(ListUnits::class)->assertTableBulkActionHidden('listOnMarketplace');
});

it('shows a boost as featured only until it ends', function () {
    marketplaceOperator(Client::create(['slug' => 'mkt-five', 'name' => 'Mkt Five', 'status' => 'active']));
    $unit = marketplaceUnit('R1');

    $unit->update(['featured_until' => now()->addDays(7)]);
    expect($unit->fresh()->isFeatured())->toBeTrue();

    $unit->update(['featured_until' => now()->subMinute()]);
    expect($unit->fresh()->isFeatured())->toBeFalse();
});

it('reserves PMS addresses so no client can take them', function (string $slug) {
    expect(ReservedSlugs::isReserved($slug))->toBeTrue();
})->with(['browse', 'admin', 'manage', 'privacy', 'Browse', 'livewire-ca5024f5', 'api']);

it('refuses a reserved workspace address for a new client', function () {
    test()->actingAs(SuperAdminUser::create([
        'name' => 'Slug Admin', 'email' => 'slug-admin@pms.local', 'password' => 'password',
    ]), 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateClient::class)
        ->fillForm(['name' => 'Browse Ltd', 'slug' => 'browse', 'status' => 'trial'])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    expect(Client::query()->where('slug', 'browse')->exists())->toBeFalse();
});

it('marks the seeded demo workspace as a demo', function () {
    $this->artisan('demo:seed', ['--slug' => 'demo-mkt'])->assertSuccessful();

    expect(Client::query()->where('slug', 'demo-mkt')->value('is_demo'))->toBeTrue();
});
