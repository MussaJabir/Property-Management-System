<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\CmsPage;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\SuperAdminUser;
use App\Models\Unit;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Seed a representative slice of tenant-scoped data inside a client's context.
 */
function purgeSeedClient(Client $client): void
{
    Tenancy::initialize($client);

    $location = Location::create([
        'name' => 'Loc '.$client->slug,
        'region' => 'Dar es Salaam',
        'district' => 'Ilala',
    ]);

    $property = Property::create([
        'location_id' => $location->id,
        'name' => 'Prop '.$client->slug,
        'type' => 'residential',
        'status' => 'active',
    ]);

    Unit::create([
        'property_id' => $property->id,
        'code' => 'U1',
        'type' => 'room',
        'rent_amount' => 100_00,
        'status' => 'vacant',
    ]);

    Renter::create([
        'type' => 'individual',
        'full_name' => 'Renter '.$client->slug,
        'phone' => '+255712345678',
    ]);

    Tenancy::end();
}

beforeEach(function () {
    $this->clientA = Client::create(['slug' => 'purge-a', 'name' => 'Purge A Ltd', 'status' => 'active']);
    $this->clientB = Client::create(['slug' => 'purge-b', 'name' => 'Purge B Ltd', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('archiving a client (soft delete) keeps all its data and roles intact', function () {
    purgeSeedClient($this->clientA);

    $this->clientA->delete();

    expect($this->clientA->fresh()->trashed())->toBeTrue()
        ->and(Property::withoutGlobalScopes()->where('tenant_id', $this->clientA->id)->count())->toBe(1)
        ->and(Unit::withoutGlobalScopes()->where('tenant_id', $this->clientA->id)->count())->toBe(1)
        ->and(Renter::withoutGlobalScopes()->where('tenant_id', $this->clientA->id)->count())->toBe(1)
        ->and(Role::where('tenant_id', $this->clientA->id)->count())->toBe(4);
});

it('purging a client permanently removes all of its tenant-scoped data', function () {
    purgeSeedClient($this->clientA);

    $this->clientA->forceDelete();

    expect(Client::withTrashed()->whereKey($this->clientA->id)->exists())->toBeFalse();

    foreach ([Location::class, Property::class, Unit::class, Renter::class, CmsPage::class, ExpenseCategory::class] as $model) {
        expect($model::withoutGlobalScopes()->where('tenant_id', $this->clientA->id)->count())
            ->toBe(0, "{$model} rows should be gone after purge");
    }
});

it('purging a client removes its Spatie roles (which the FK cascade cannot reach)', function () {
    expect(Role::where('tenant_id', $this->clientA->id)->count())->toBe(4);

    $this->clientA->forceDelete();

    expect(Role::where('tenant_id', $this->clientA->id)->count())->toBe(0);
});

it('purging a client deletes its uploaded files from storage', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    Tenancy::initialize($this->clientA);
    $location = Location::create(['name' => 'L', 'region' => 'Dar es Salaam', 'district' => 'Ilala']);
    $property = Property::create([
        'location_id' => $location->id,
        'name' => 'With Photo',
        'type' => 'residential',
        'status' => 'active',
    ]);
    // Keep the UploadedFile in a variable so its temp file isn't GC'd before
    // Spatie reads it; pass the object (not a path string) to addMedia.
    $file = UploadedFile::fake()->image('photo.jpg', 20, 20);
    $property->addMedia($file)->toMediaCollection('photos');
    $media = $property->getFirstMedia('photos');
    $path = $media->getPathRelativeToRoot();
    Tenancy::end();

    expect($media)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->clientA->forceDelete();

    expect(Media::whereKey($media->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

it('purging one client leaves another client’s data and roles untouched', function () {
    purgeSeedClient($this->clientA);
    purgeSeedClient($this->clientB);

    $this->clientA->forceDelete();

    expect(Client::whereKey($this->clientB->id)->exists())->toBeTrue()
        ->and(Property::withoutGlobalScopes()->where('tenant_id', $this->clientB->id)->count())->toBe(1)
        ->and(Unit::withoutGlobalScopes()->where('tenant_id', $this->clientB->id)->count())->toBe(1)
        ->and(Renter::withoutGlobalScopes()->where('tenant_id', $this->clientB->id)->count())->toBe(1)
        ->and(Role::where('tenant_id', $this->clientB->id)->count())->toBe(4);
});

it('the purge action requires the exact client name, then permanently deletes', function () {
    $admin = SuperAdminUser::create([
        'name' => 'Purge Admin',
        'email' => 'purge-admin@pms.local',
        'password' => 'password',
    ]);
    $this->actingAs($admin, 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Must archive before purge is even offered.
    $this->clientA->delete();

    // Wrong name → validation error, client survives.
    Livewire::test(ListClients::class)
        ->callTableAction('purge', $this->clientA, data: ['confirmation' => 'Not The Name'])
        ->assertHasTableActionErrors(['confirmation']);

    expect(Client::withTrashed()->whereKey($this->clientA->id)->exists())->toBeTrue();

    // Exact name → permanently purged.
    Livewire::test(ListClients::class)
        ->callTableAction('purge', $this->clientA, data: ['confirmation' => $this->clientA->name])
        ->assertHasNoTableActionErrors();

    expect(Client::withTrashed()->whereKey($this->clientA->id)->exists())->toBeFalse();
});
