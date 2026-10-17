<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Filament\Operator\Resources\Leases\Pages\CreateLease;
use App\Filament\Operator\Resources\Properties\Pages\CreateProperty;
use App\Filament\Operator\Resources\Renters\Pages\CreateRenter;
use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Filament\Operator\Resources\Units\Pages\CreateUnit;
use App\Filament\Operator\Resources\Units\UnitResource;
use App\Models\Client;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'flowco', 'name' => 'Flow Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina',
        'email' => 'amina@flow.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');
    $owner->forceFill(['onboarding_completed_at' => now()])->save();

    $this->actingAs($owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    $this->location = Location::create(['name' => 'Madale', 'region' => 'Dar', 'district' => 'Kinondoni']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('goes from a new property straight to adding its units, property chosen', function () {
    Livewire::test(CreateProperty::class)
        ->assertFormFieldHidden('status')
        ->fillForm(['name' => 'Madale Court', 'location_id' => $this->location->id, 'type' => 'residential'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Madale Court saved');

    $property = Property::query()->where('name', 'Madale Court')->sole();
    expect($property->status)->toBe('active');

    Livewire::withQueryParams(['property' => $property->id])
        ->test(CreateUnit::class)
        ->assertFormFieldHidden('status')
        ->assertSchemaStateSet(['property_id' => $property->id])
        ->fillForm(['code' => 'R1', 'type' => 'room', 'rent_amount' => 200_000, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Unit::query()->where('code', 'R1')->sole()->status)->toBe(Unit::STATUS_VACANT);
});

it('redirects a new property to its units page', function () {
    Livewire::test(CreateProperty::class)
        ->fillForm(['name' => 'Kijitonyama Flats', 'location_id' => $this->location->id, 'type' => 'residential'])
        ->call('create')
        ->assertRedirect(UnitResource::getUrl('create', ['property' => Property::query()->where('name', 'Kijitonyama Flats')->value('id')]));
});

it('takes a new renter on to a lease when a unit is free, else back to the list', function () {
    Livewire::test(CreateRenter::class)
        ->fillForm(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Asha Bakari', 'phone' => '0712555666'])
        ->call('create')
        ->assertRedirect(RenterResource::getUrl('index'));

    $property = Property::create(['location_id' => $this->location->id, 'name' => 'Block', 'status' => 'active']);
    Unit::create(['property_id' => $property->id, 'code' => 'A1', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);

    Livewire::test(CreateRenter::class)
        ->fillForm(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Said Omari', 'phone' => '0712777888'])
        ->call('create')
        ->assertRedirect(LeaseResource::getUrl('create', ['renter' => Renter::query()->where('full_name', 'Said Omari')->value('id')]));

    $renter = Renter::query()->where('full_name', 'Said Omari')->sole();
    Livewire::withQueryParams(['renter' => $renter->id])
        ->test(CreateLease::class)
        ->assertSchemaStateSet(['renter_id' => $renter->id]);
});
