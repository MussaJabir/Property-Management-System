<?php

use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'setco', 'name' => 'Set Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);

    $this->owner = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Owner',
        'email' => 'owner@setco.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->owner->assignRole('owner');

    $this->manager = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Manager',
        'email' => 'manager@setco.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->manager->assignRole('manager');

    Filament::setCurrentPanel(Filament::getPanel('operator'));
});

it('lets an owner open the settings page', function () {
    $this->actingAs($this->owner, 'web');

    Livewire::test(WorkspaceSettings::class)->assertOk();
});

it('blocks a non-owner operator from settings', function () {
    $this->actingAs($this->manager, 'web');

    expect(WorkspaceSettings::canAccess())->toBeFalse();
});

it('changes the signed-in owner password from the Security tab', function () {
    $this->actingAs($this->owner, 'web');

    Livewire::test(WorkspaceSettings::class)
        ->set('data.new_password', 'brandnew123')
        ->set('data.new_password_confirmation', 'brandnew123')
        ->call('save')
        ->assertHasNoErrors();

    $this->owner->refresh();
    expect(Hash::check('brandnew123', $this->owner->password))->toBeTrue();
    expect((bool) $this->owner->must_change_password)->toBeFalse();
});

it('saves workspace fields without touching the password when left blank', function () {
    $this->actingAs($this->owner, 'web');
    $originalHash = $this->owner->password;

    Livewire::test(WorkspaceSettings::class)
        ->set('data.name', 'Set Co Renamed')
        ->set('data.brand_primary_color', '#123456')
        ->call('save')
        ->assertHasNoErrors();

    $this->client->refresh();
    $this->owner->refresh();
    expect($this->client->name)->toBe('Set Co Renamed')
        ->and($this->client->brand_primary_color)->toBe('#123456')
        ->and($this->owner->password)->toBe($originalHash);
});
