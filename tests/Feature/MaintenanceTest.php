<?php

use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceUpdate;
use App\Models\Property;
use App\Models\Unit;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->clientA = Client::create(['slug' => 'a', 'name' => 'Client A', 'status' => 'active']);
    $this->clientB = Client::create(['slug' => 'b', 'name' => 'Client B', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function makeUnitForMaintenance(): Unit
{
    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $prop = Property::create([
        'location_id' => $loc->id,
        'name' => 'P',
        'type' => 'residential',
        'status' => 'active',
    ]);

    return Unit::create([
        'property_id' => $prop->id,
        'code' => 'U1',
        'type' => 'room',
        'rent_amount' => 0,
        'status' => 'vacant',
    ]);
}

function makeRequest(string $priority = 'medium'): MaintenanceRequest
{
    $unit = makeUnitForMaintenance();

    return MaintenanceRequest::create([
        'unit_id' => $unit->id,
        'title' => 'Tap dripping',
        'description' => 'Kitchen tap drips overnight.',
        'priority' => $priority,
        'status' => 'pending',
        'reported_at' => now(),
    ]);
}

it('walks a request through pending → in_progress → completed and writes audit rows', function () {
    Tenancy::initialize($this->clientA);

    $request = makeRequest();
    expect($request->status)->toBe('pending');
    expect(MaintenanceUpdate::count())->toBe(0);

    $request->start(note: 'Plumber called.');
    $request->refresh();
    expect($request->status)->toBe('in_progress');
    expect($request->started_at)->not->toBeNull();

    $request->complete(costCents: 25_000_00, note: 'New washer fitted.');
    $request->refresh();
    expect($request->status)->toBe('completed');
    expect($request->completed_at)->not->toBeNull();
    expect($request->cost)->toBe(25_000_00);

    $updates = MaintenanceUpdate::where('maintenance_request_id', $request->id)->orderBy('created_at')->get();
    expect($updates)->toHaveCount(2);
    expect($updates->pluck('status_change')->all())->toBe(['in_progress', 'completed']);
});

it('refuses to complete a request that has not been started', function () {
    Tenancy::initialize($this->clientA);

    $request = makeRequest();

    expect(fn () => $request->complete())->toThrow(RuntimeException::class);
});

it('allows cancellation from both pending and in_progress', function () {
    Tenancy::initialize($this->clientA);

    $pending = makeRequest();
    $pending->cancel(reason: 'Duplicate report.');
    expect($pending->fresh()->status)->toBe('cancelled');

    $started = makeRequest();
    $started->start();
    $started->cancel(reason: 'Renter withdrew.');
    expect($started->fresh()->status)->toBe('cancelled');

    expect(fn () => $pending->cancel())->toThrow(RuntimeException::class);
});

it('records plain notes without changing status', function () {
    Tenancy::initialize($this->clientA);

    $request = makeRequest();
    $request->start();
    $request->addNote('Waiting on spare parts.');

    $notes = MaintenanceUpdate::where('maintenance_request_id', $request->id)
        ->whereNull('status_change')
        ->get();

    expect($notes)->toHaveCount(1);
    expect($notes->first()->note)->toBe('Waiting on spare parts.');
    expect($request->fresh()->status)->toBe('in_progress');
});

it('keeps maintenance requests isolated between clients', function () {
    Tenancy::initialize($this->clientA);
    makeRequest();

    Tenancy::end();
    Tenancy::initialize($this->clientB);

    expect(MaintenanceRequest::count())->toBe(0);
});

it('seeds the six default expense categories on client creation', function () {
    // clientA / clientB are created in beforeEach — ClientObserver fires there.
    Tenancy::initialize($this->clientA);

    expect(ExpenseCategory::count())->toBe(6);
    expect(ExpenseCategory::pluck('name')->all())
        ->toContain('Repair', 'Cleaning', 'Security', 'Utilities', 'Tax', 'Other');
});
