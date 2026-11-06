<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\Marketplace;
use App\Models\Client;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SuperAdminUser;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Services\Marketplace\ListingSearch;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->client = Client::create([
        'slug' => 'boostco', 'name' => 'Boost Co', 'status' => 'active',
        'plan_id' => Plan::query()->where('slug', 'pro')->value('id'),
    ]);
    tenancy()->initialize($this->client);
    $property = Property::create([
        'location_id' => Location::create(['name' => 'Njiro', 'district' => 'Arusha City', 'region' => 'Arusha'])->id,
        'name' => 'Njiro Heights', 'status' => 'active',
    ]);
    $this->unit = Unit::create([
        'property_id' => $property->id, 'code' => 'R101', 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 350_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly', 'on_marketplace' => true,
    ]);
    Tenancy::end();

    $this->actingAs(SuperAdminUser::create([
        'name' => 'BJP Admin', 'email' => 'market-admin@pms.local', 'password' => 'password',
    ]), 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lists every unit landlords put on the marketplace', function () {
    Livewire::test(Marketplace::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->unit])
        ->assertSee('1 on /browse now');
});

it('features a unit for the chosen days and records the payment reference', function () {
    $this->travelTo(now()->startOfDay());

    Livewire::test(Marketplace::class)
        ->callTableAction('feature', $this->unit, data: ['days' => 14, 'reference' => 'QK7HX2P9TL'])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $unit = $this->unit->fresh();
    expect($unit->isFeatured())->toBeTrue()
        ->and($unit->featured_until->toDateString())->toBe(now()->addDays(14)->toDateString());

    $log = Activity::query()->where('log_name', 'marketplace')->latest('id')->first();
    expect($log->properties['reference'])->toBe('QK7HX2P9TL')
        ->and($log->properties['days'])->toBe(14)
        ->and($log->properties['unit_id'])->toBe($unit->id);

    expect(app(ListingSearch::class)->search()->first()->id)->toBe($unit->id);
});

it('adds to a boost that is still running', function () {
    $this->travelTo(now()->startOfDay());
    $this->unit->forceFill(['featured_until' => now()->addDays(3)])->save();

    Livewire::test(Marketplace::class)
        ->callTableAction('feature', $this->unit, data: ['days' => 7, 'reference' => 'AB12']);

    expect($this->unit->fresh()->featured_until->toDateString())->toBe(now()->addDays(10)->toDateString());
});

it('needs a payment reference to feature a unit', function () {
    Livewire::test(Marketplace::class)
        ->callTableAction('feature', $this->unit, data: ['days' => 7, 'reference' => ''])
        ->assertHasTableActionErrors(['reference' => 'required']);

    expect($this->unit->fresh()->isFeatured())->toBeFalse();
});

it('ends a boost early', function () {
    $this->unit->forceFill(['featured_until' => now()->addDays(5)])->save();

    Livewire::test(Marketplace::class)->callTableAction('endFeature', $this->unit);

    expect($this->unit->fresh()->isFeatured())->toBeFalse();
});

it('hides and shows demo listings on /browse', function () {
    Livewire::test(Marketplace::class)->callAction('toggleDemo');
    expect(SystemSetting::value(ListingSearch::HIDE_DEMO_SETTING))->toBeTrue();

    Livewire::test(Marketplace::class)->callAction('toggleDemo');
    expect(SystemSetting::value(ListingSearch::HIDE_DEMO_SETTING))->toBeFalse();
});

it('cannot feature a demo workspace unit', function () {
    $this->client->update(['is_demo' => true]);

    Livewire::test(Marketplace::class)->assertTableActionHidden('feature', $this->unit);
});

it('is for BJP only, not for landlords', function () {
    auth('super_admin')->logout();

    $this->get('/admin/marketplace')->assertRedirect();
});

it('links the home page to the marketplace', function () {
    $this->get('/')->assertOk()->assertSee(route('marketplace.browse'), false)->assertSee('Find a place');
});
