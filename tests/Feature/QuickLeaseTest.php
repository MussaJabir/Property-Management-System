<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Leases\Pages\CreateLease;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'quickco', 'name' => 'Quick Co', 'status' => 'active']);
    $this->other = Client::create(['slug' => 'otherquick', 'name' => 'Other Quick', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina',
        'email' => 'amina@quick.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function quickUnit(Client $client, int $rent = 450_000_00): Unit
{
    tenancy()->initialize($client);
    $location = Location::firstOrCreate(['name' => 'Area '.$client->id], ['region' => 'Dar', 'district' => 'Kinondoni']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Madale Court', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'B'.random_int(1, 999), 'status' => Unit::STATUS_VACANT, 'rent_amount' => $rent, 'rent_currency' => 'TZS', 'billing_cycle' => 'quarterly']);
    Tenancy::end();

    return $unit;
}

function quickSignIn(User $user, Client $client): void
{
    test()->actingAs($user, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($client);
}

it('starts a lease from one screen: unit, new renter added inline, first invoice issued', function () {
    $unit = quickUnit($this->client);
    quickSignIn($this->owner, $this->client);

    $page = Livewire::test(CreateLease::class)
        ->fillForm(['unit_id' => $unit->id])
        // Rent and billing come from the unit.
        ->assertSchemaStateSet(['rent_amount' => 450_000, 'billing_cycle' => 'quarterly', 'activate_now' => true])
        ->callAction(TestAction::make('createOption')->schemaComponent('renter_id'), [
            'full_name' => 'Grace Mollel', 'phone' => '0754123456',
        ]);

    $renter = Renter::query()->where('full_name', 'Grace Mollel')->sole();
    expect((string) $renter->phone)->toBe('+255754123456');

    $page->assertSchemaStateSet(['renter_id' => $renter->id])
        ->fillForm(['deposit_amount' => 450_000])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Lease started');

    $lease = Lease::query()->where('renter_id', $renter->id)->sole();
    $invoice = Invoice::query()->where('lease_id', $lease->id)->sole();

    expect($lease->status)->toBe(Lease::STATUS_ACTIVE)
        ->and($lease->rent_amount)->toBe(450_000_00)
        ->and($unit->fresh()?->status)->toBe(Unit::STATUS_OCCUPIED)
        ->and($invoice->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice->total_amount)->toBe(900_000_00);
});

it('keeps the lease pending, with no invoice, when "Start the lease now" is off', function () {
    $unit = quickUnit($this->client);
    quickSignIn($this->owner, $this->client);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Juma Said', 'phone' => '0713000111']);

    Livewire::test(CreateLease::class)
        ->fillForm(['unit_id' => $unit->id, 'renter_id' => $renter->id, 'activate_now' => false])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Lease saved as pending');

    $lease = Lease::query()->where('renter_id', $renter->id)->sole();
    expect($lease->status)->toBe(Lease::STATUS_PENDING)
        ->and($unit->fresh()?->status)->toBe(Unit::STATUS_VACANT)
        ->and(Invoice::query()->where('lease_id', $lease->id)->exists())->toBeFalse();
});

it('preselects the unit from Today\'s vacant list, but only this workspace\'s units', function () {
    $unit = quickUnit($this->client);
    $theirs = quickUnit($this->other);
    quickSignIn($this->owner, $this->client);

    Livewire::withQueryParams(['unit' => $unit->id])
        ->test(CreateLease::class)
        ->assertSchemaStateSet(['unit_id' => $unit->id, 'rent_amount' => 450_000]);

    Livewire::withQueryParams(['unit' => $theirs->id])
        ->test(CreateLease::class)
        ->assertSchemaStateSet(['unit_id' => null]);
});
