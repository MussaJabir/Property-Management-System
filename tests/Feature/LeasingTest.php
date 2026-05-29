<?php

use App\Models\Client;
use App\Models\Lease;
use App\Models\LeaseHistory;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
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

/**
 * Helper: build a Location + Property + vacant Unit inside the active
 * tenant context and return the trio. Keeps test bodies focused on the
 * thing under test.
 */
function makeUnit(string $code = 'U1', int $rentCents = 50_000_00): array
{
    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $prop = Property::create([
        'location_id' => $loc->id,
        'name' => 'Prop '.$code,
        'type' => 'residential',
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'property_id' => $prop->id,
        'code' => $code,
        'type' => 'room',
        'rent_amount' => $rentCents,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'status' => 'vacant',
    ]);

    return [$loc, $prop, $unit];
}

function makeRenter(string $name = 'John Doe', string $type = 'individual'): Renter
{
    return Renter::create([
        'type' => $type,
        'full_name' => $name,
        'phone' => '+255712345678',
    ]);
}

it('encrypts the renter NIDA and TIN at rest', function () {
    Tenancy::initialize($this->clientA);

    $renter = Renter::create([
        'type' => 'individual',
        'full_name' => 'Asha Mwangi',
        'phone' => '+255712345678',
        'nida_number' => '19900101-12345-67890-12',
        'tin_number' => '123-456-789',
    ]);

    // Through the model, casts decrypt automatically.
    expect($renter->fresh()->nida_number)->toBe('19900101-12345-67890-12');
    expect($renter->fresh()->tin_number)->toBe('123-456-789');

    // The raw DB column should NOT contain the plaintext.
    $row = DB::table('renters')->where('id', $renter->id)->first();
    expect($row->nida_number)->not->toBe('19900101-12345-67890-12');
    expect($row->nida_number)->not->toBeNull();
});

it('normalizes Tanzanian local-format phones to E.164', function () {
    Tenancy::initialize($this->clientA);

    $renter = Renter::create([
        'type' => 'individual',
        'full_name' => 'Mussa',
        'phone' => '0712345678',
    ]);

    // Cast returns a PhoneNumber object that stringifies to E.164.
    expect((string) $renter->fresh()->phone)->toBe('+255712345678');
});

it('renders a sensible display name for individuals and businesses', function () {
    Tenancy::initialize($this->clientA);

    $individual = makeRenter('Halima');
    $business = Renter::create([
        'type' => 'business',
        'full_name' => 'Contact Person',
        'business_name' => 'Acme Ltd',
        'phone' => '+255712345600',
    ]);

    expect($individual->display_name)->toBe('Halima');
    expect($business->display_name)->toBe('Acme Ltd (Contact Person)');
});

it('isolates renters and leases between clients', function () {
    Tenancy::initialize($this->clientA);
    [, , $unitA] = makeUnit('A1');
    $renterA = makeRenter('Renter A');
    Lease::create([
        'renter_id' => $renterA->id,
        'unit_id' => $unitA->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => $unitA->rent_amount,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'pending',
    ]);

    Tenancy::end();
    Tenancy::initialize($this->clientB);

    expect(Renter::count())->toBe(0);
    expect(Lease::count())->toBe(0);
});

it('activates a pending lease, marks the unit occupied, and writes history', function () {
    Tenancy::initialize($this->clientA);

    [, , $unit] = makeUnit('A1');
    $renter = makeRenter();

    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => $unit->rent_amount,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'pending',
    ]);

    $lease->activate();

    $lease->refresh();
    $unit->refresh();

    expect($lease->status)->toBe('active');
    expect($lease->activated_at)->not->toBeNull();
    expect($unit->status)->toBe('occupied');

    $history = LeaseHistory::where('lease_id', $lease->id)->where('action', 'activated')->first();
    expect($history)->not->toBeNull();
    expect($history->after['status'])->toBe('active');
});

it('refuses to activate a lease that is not pending', function () {
    Tenancy::initialize($this->clientA);

    [, , $unit] = makeUnit('A1');
    $renter = makeRenter();

    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => $unit->rent_amount,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'active',
    ]);

    expect(fn () => $lease->activate())->toThrow(RuntimeException::class);
});

it('terminates an active lease, frees the unit, and records the reason', function () {
    Tenancy::initialize($this->clientA);

    [, , $unit] = makeUnit('A1');
    $renter = makeRenter();

    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => $unit->rent_amount,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'pending',
    ]);
    $lease->activate();

    $lease->terminate('Renter relocated.');

    $lease->refresh();
    $unit->refresh();

    expect($lease->status)->toBe('terminated');
    expect($lease->ended_at)->not->toBeNull();
    expect($unit->status)->toBe('vacant');

    $history = LeaseHistory::where('lease_id', $lease->id)->where('action', 'terminated')->first();
    expect($history->reason)->toBe('Renter relocated.');
});

it('ends an active lease naturally and marks the unit vacant', function () {
    Tenancy::initialize($this->clientA);

    [, , $unit] = makeUnit('A1');
    $renter = makeRenter();

    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->toDateString(),
        'rent_amount' => $unit->rent_amount,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'pending',
    ]);
    $lease->activate();

    $lease->end();

    $lease->refresh();
    $unit->refresh();

    expect($lease->status)->toBe('ended');
    expect($unit->status)->toBe('vacant');
});

it('exposes a human billing cycle label that respects custom months', function () {
    Tenancy::initialize($this->clientA);

    [, , $unit] = makeUnit('A1');
    $renter = makeRenter();

    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => 0,
        'currency' => 'TZS',
        'billing_cycle' => 'custom',
        'billing_cycle_months' => 9,
        'payment_due_day' => 1,
        'status' => 'pending',
    ]);

    expect($lease->billing_cycle_label)->toBe('Every 9 months');
    expect($lease->billingCycleMonths())->toBe(9);
});
