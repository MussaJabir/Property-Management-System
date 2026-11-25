<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Inspections\Pages\CreateInspection;
use App\Filament\Operator\Resources\Inspections\Pages\EditInspection;
use App\Filament\Operator\Resources\Inspections\Pages\ListInspections;
use App\Filament\Operator\Resources\Inspections\Pages\ViewInspection;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-26 09:00'));
    $this->client = Client::create(['slug' => 'inspco', 'name' => 'Insp Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@insp.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $this->unit = Unit::create(['property_id' => $property->id, 'code' => 'A3', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Jean Uwase', 'phone' => '+255712000111']);
    $this->lease = Lease::create([
        'unit_id' => $this->unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-01-01', 'rent_amount' => 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 5,
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function inspectionOf(string $type, array $items): Inspection
{
    return Inspection::create([
        'unit_id' => test()->unit->id, 'lease_id' => test()->lease->id, 'type' => $type,
        'inspected_on' => today()->toDateString(), 'inspector_user_id' => test()->owner->id, 'items' => $items,
    ]);
}

it('locks an inspection once completed', function () {
    $empty = Inspection::create(['unit_id' => $this->unit->id, 'type' => 'routine', 'inspected_on' => today()]);
    expect($empty->status)->toBe('draft')
        ->and(fn () => $empty->complete())->toThrow(RuntimeException::class);

    $inspection = inspectionOf('move_in', [['area' => 'Kitchen', 'condition' => 'good', 'note' => null]]);
    $inspection->complete();

    expect($inspection->fresh()?->isCompleted())->toBeTrue()
        ->and($inspection->fresh()?->completed_at)->not->toBeNull()
        ->and($this->owner->can('update', $inspection->fresh()))->toBeFalse()
        ->and($this->owner->can('view', $inspection->fresh()))->toBeTrue()
        ->and(fn () => $inspection->complete())->toThrow(RuntimeException::class);
});

it('shows which areas are worse at move-out than at move-in', function () {
    inspectionOf('move_in', [
        ['area' => 'Kitchen', 'condition' => 'good'],
        ['area' => 'Walls and paint', 'condition' => 'fair'],
        ['area' => 'Windows', 'condition' => 'good'],
    ])->complete();

    $moveOut = inspectionOf('move_out', [
        ['area' => 'kitchen', 'condition' => 'damaged'],
        ['area' => 'Walls and paint', 'condition' => 'fair'],
        ['area' => 'Windows', 'condition' => 'good'],
    ]);

    expect($moveOut->worseThanMoveIn())->toBe(['kitchen' => ['Good', 'Damaged']]);
});

it('keeps inspections to their own client', function () {
    inspectionOf('routine', [['area' => 'Roof', 'condition' => 'good']]);
    Tenancy::end();
    $other = Client::create(['slug' => 'otherinsp', 'name' => 'Other Insp', 'status' => 'active']);
    tenancy()->initialize($other);

    expect(Inspection::query()->count())->toBe(0);
});

it('records and completes an inspection in the panel', function () {
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(CreateInspection::class)
        ->fillForm(['unit_id' => $this->unit->id])
        ->assertFormSet(['lease_id' => $this->lease->id])
        ->fillForm(['type' => 'move_in', 'electricity_reading' => '04512', 'keys_handed' => 2])
        ->call('create')
        ->assertHasNoFormErrors();

    $inspection = Inspection::query()->sole();
    expect($inspection->items)->toHaveCount(count(Inspection::DEFAULT_AREAS))
        ->and($inspection->keys_handed)->toBe(2);

    Livewire::test(EditInspection::class, ['record' => $inspection->getRouteKey()])
        ->callAction('complete');
    expect($inspection->fresh()?->isCompleted())->toBeTrue();

    Livewire::test(ViewInspection::class, ['record' => $inspection->getRouteKey()])->assertOk();
    Livewire::test(ListInspections::class)->assertOk()->assertSee('Jean Uwase');
});
