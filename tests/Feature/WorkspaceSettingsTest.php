<?php

use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Models\Client;
use App\Models\Plan;
use App\Models\User;
use App\Services\RentInvoiceGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

it('turns automatic rent invoices off and on from Preferences', function () {
    $this->actingAs($this->owner, 'web');

    Livewire::test(WorkspaceSettings::class)
        ->assertSet('data.auto_invoices', true)
        ->set('data.auto_invoices', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(RentInvoiceGenerator::enabledFor($this->client->refresh()))->toBeFalse()
        ->and($this->client->settings['default_locale'])->toBe('en');

    Livewire::test(WorkspaceSettings::class)->assertSet('data.auto_invoices', false);
});

it('shows the plan the workspace is on, with its limits, and no internal labels', function () {
    $plan = Plan::create([
        'name' => 'Pro', 'slug' => 'pro-test', 'price_tzs' => 14_900_000, 'billing_period' => 'monthly',
        'max_properties' => 15, 'max_units' => 300, 'max_operators' => 30, 'features' => [],
    ]);
    $this->client->update(['plan_id' => $plan->id]);
    $this->actingAs($this->owner, 'web');

    Livewire::test(WorkspaceSettings::class)
        ->assertSee($plan->name)
        ->assertSee('What it includes')
        ->assertSee('0 of 300 units')
        ->assertDontSee('Security note')
        ->assertDontSee('Sub help')
        ->assertSee('Active');
});

it('keeps logos on public storage and shows them on the website', function () {
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('local')->put('client-logos/old.png', 'png-bytes');
    $this->client->update(['logo_path' => 'client-logos/old.png']);

    // A logo saved on the private disk before the move is copied over.
    expect($this->client->fresh()->logoUrl())->toContain('client-logos/old.png')
        ->and(Storage::disk('public')->exists('client-logos/old.png'))->toBeTrue();

    $this->get('/setco/units')->assertOk()->assertSee('client-logos/old.png', false);
});
