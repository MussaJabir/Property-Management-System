<?php

use App\Models\Client;
use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->clientA = Client::create([
        'slug' => 'a',
        'name' => 'Client A',
        'status' => 'active',
    ]);

    $this->clientB = Client::create([
        'slug' => 'b',
        'name' => 'Client B',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('scopes locations and properties to the current tenant', function () {
    Tenancy::initialize($this->clientA);

    $locA = Location::create([
        'name' => 'Kariakoo Area',
        'region' => 'Dar es Salaam',
        'district' => 'Ilala',
    ]);
    $propA = Property::create([
        'location_id' => $locA->id,
        'name' => 'Tower A',
        'type' => 'residential',
        'status' => 'active',
    ]);

    Tenancy::end();
    Tenancy::initialize($this->clientB);

    $locB = Location::create([
        'name' => 'Mwanza Area',
        'region' => 'Mwanza',
        'district' => 'Nyamagana',
    ]);
    Property::create([
        'location_id' => $locB->id,
        'name' => 'Tower B',
        'type' => 'residential',
        'status' => 'active',
    ]);

    // Still in Client B's context — should NOT see Client A's records
    expect(Property::count())->toBe(1);
    expect(Property::first()->name)->toBe('Tower B');
    expect(Location::count())->toBe(1);

    // Switch back to Client A
    Tenancy::end();
    Tenancy::initialize($this->clientA);

    expect(Property::count())->toBe(1);
    expect(Property::first()->name)->toBe('Tower A');
});

it('soft-deletes properties and units', function () {
    Tenancy::initialize($this->clientA);

    $loc = Location::create([
        'name' => 'Loc',
        'region' => 'R',
        'district' => 'D',
    ]);
    $prop = Property::create([
        'location_id' => $loc->id,
        'name' => 'Prop',
        'type' => 'residential',
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'property_id' => $prop->id,
        'code' => 'U1',
        'type' => 'room',
        'rent_amount' => 100_00,
        'status' => 'vacant',
    ]);

    $prop->delete();
    $unit->delete();

    expect(Property::find($prop->id))->toBeNull();
    expect(Unit::find($unit->id))->toBeNull();

    expect(Property::withTrashed()->find($prop->id))->not->toBeNull();
    expect(Unit::withTrashed()->find($unit->id))->not->toBeNull();
});

it('enforces unique unit code per property', function () {
    Tenancy::initialize($this->clientA);

    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $prop = Property::create([
        'location_id' => $loc->id,
        'name' => 'P',
        'type' => 'residential',
        'status' => 'active',
    ]);

    Unit::create([
        'property_id' => $prop->id,
        'code' => 'A1',
        'type' => 'room',
        'rent_amount' => 0,
    ]);

    expect(fn () => Unit::create([
        'property_id' => $prop->id,
        'code' => 'A1',
        'type' => 'room',
        'rent_amount' => 0,
    ]))->toThrow(QueryException::class);
});

it('vacantForClient returns only vacant units for the client', function () {
    Tenancy::initialize($this->clientA);

    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $prop = Property::create([
        'location_id' => $loc->id,
        'name' => 'P',
        'type' => 'residential',
        'status' => 'active',
    ]);

    Unit::create(['property_id' => $prop->id, 'code' => 'V1', 'type' => 'room', 'rent_amount' => 0, 'status' => 'vacant']);
    Unit::create(['property_id' => $prop->id, 'code' => 'V2', 'type' => 'room', 'rent_amount' => 0, 'status' => 'vacant']);
    Unit::create(['property_id' => $prop->id, 'code' => 'O1', 'type' => 'room', 'rent_amount' => 0, 'status' => 'occupied']);

    Tenancy::end();

    $vacant = Unit::vacantForClient($this->clientA->id)->get();

    expect($vacant)->toHaveCount(2)
        ->and($vacant->pluck('code')->all())->toContain('V1', 'V2');
});

it('formats rent as TZS with thousands separator', function () {
    Tenancy::initialize($this->clientA);

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
        'rent_amount' => 35_000_000, // TZS 350,000
        'rent_currency' => 'TZS',
    ]);

    expect($unit->formatted_rent)->toBe('TZS 350,000');
});
