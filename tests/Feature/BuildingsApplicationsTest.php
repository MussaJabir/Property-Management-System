<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Buildings\Pages\ListBuildings;
use App\Filament\Operator\Resources\RentalApplications\Pages\CreateRentalApplication;
use App\Filament\Operator\Resources\RentalApplications\Pages\ListRentalApplications;
use App\Filament\Operator\Resources\Units\Pages\EditUnit;
use App\Models\Building;
use App\Models\Client;
use App\Models\Location;
use App\Models\Property;
use App\Models\RentalApplication;
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
    $this->client = Client::create(['slug' => 'blockco', 'name' => 'Block Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@block.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $this->blockA = Building::create(['property_id' => $this->property->id, 'name' => 'Block A', 'floors' => 4]);
    $this->unit = Unit::create(['property_id' => $this->property->id, 'building_id' => $this->blockA->id, 'floor' => '2', 'code' => 'A2-01', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function blockApplication(array $overrides = []): RentalApplication
{
    return RentalApplication::create(array_merge([
        'applicant_name' => 'Jean Uwase', 'phone' => '0712000222', 'email' => 'jean@example.test',
        'id_number' => '1199880012345678', 'unit_id' => test()->unit->id, 'move_in_on' => '2026-12-01',
        'screening_notes' => 'Previous landlord: paid on time.',
    ], $overrides));
}

it('places units in a building and on a floor', function () {
    expect($this->unit->locationLabel())->toBe('Block A, floor 2')
        ->and($this->blockA->units()->count())->toBe(1);

    $loose = Unit::create(['property_id' => $this->property->id, 'code' => 'Shop 1', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    expect($loose->locationLabel())->toBeNull();

    // Removing a building keeps its units.
    $this->blockA->forceDelete();
    expect($this->unit->fresh()?->building_id)->toBeNull();
});

it('keeps buildings and applications to their own client', function () {
    blockApplication();
    Tenancy::end();
    $other = Client::create(['slug' => 'otherblock', 'name' => 'Other Block', 'status' => 'active']);
    tenancy()->initialize($other);

    expect(Building::query()->count())->toBe(0)
        ->and(RentalApplication::query()->count())->toBe(0);
});

it('turns an approved application into a renter', function () {
    $application = blockApplication();
    expect($application->fresh()?->getRawOriginal('phone'))->toBe('+255712000222')
        ->and($application->isOpen())->toBeTrue();

    $renter = $application->approve($this->owner->id, 'References fine');

    $application->refresh();
    expect($application->status)->toBe(RentalApplication::STATUS_APPROVED)
        ->and($application->renter_id)->toBe($renter->id)
        ->and($application->decided_by_user_id)->toBe($this->owner->id)
        ->and($renter->full_name)->toBe('Jean Uwase')
        ->and($renter->nida_number)->toBe('1199880012345678');

    expect(fn () => $application->reject())->toThrow(RuntimeException::class);

    // A second application from the same phone links the same renter.
    $again = blockApplication(['applicant_name' => 'Jean U.']);
    expect($again->approve()->id)->toBe($renter->id)
        ->and(Renter::query()->count())->toBe(1);
});

it('records declined and withdrawn applications with a reason', function () {
    $declined = blockApplication();
    $declined->reject($this->owner->id, 'Income too low for this unit');
    $withdrawn = blockApplication(['phone' => '0712000333']);
    $withdrawn->withdraw();

    expect($declined->fresh()?->status)->toBe('rejected')
        ->and($declined->fresh()?->decision_reason)->toBe('Income too low for this unit')
        ->and($withdrawn->fresh()?->status)->toBe('withdrawn')
        ->and(Renter::query()->count())->toBe(0);
});

it('lets staff manage buildings, units and applications in the panel', function () {
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ListBuildings::class)->assertOk()->assertSee('Block A');

    Livewire::test(EditUnit::class, ['record' => $this->unit->getRouteKey()])
        ->assertOk()
        ->assertFormSet(['building_id' => $this->blockA->id, 'floor' => '2']);

    Livewire::test(CreateRentalApplication::class)
        ->fillForm(['applicant_name' => 'Patrick Nshuti', 'phone' => '0712000444', 'unit_id' => $this->unit->id, 'monthly_income' => 900000])
        ->call('create')
        ->assertHasNoFormErrors();

    $application = RentalApplication::query()->where('applicant_name', 'Patrick Nshuti')->sole();
    expect($application->status)->toBe('received')
        ->and($application->monthly_income)->toBe(900_000_00);

    Livewire::test(ListRentalApplications::class)
        ->assertSee('Patrick Nshuti')
        ->callTableAction('approve', $application)
        ->assertHasNoTableActionErrors();

    expect($application->fresh()?->status)->toBe('approved')
        ->and(Renter::query()->where('full_name', 'Patrick Nshuti')->exists())->toBeTrue();
});
