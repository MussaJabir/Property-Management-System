<?php

use App\Filament\Auth\Login;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->client = Client::create([
        'slug' => 'acme',
        'name' => 'Acme Properties',
        'status' => 'active',
    ]);

    // ClientObserver seeds roles scoped to this client's tenant_id.
    // Tell Spatie which team scope to use before assignRole.
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);

    $this->operator = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Alice Operator',
        'email' => 'alice@acme.local',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->operator->assignRole('owner');
});

it('redirects unauthenticated visitors away from /manage', function () {
    $this->get('/manage')->assertRedirect('/manage/login');
});

it('serves the operator login page', function () {
    $this->get('/manage/login')->assertOk();
});

it('lets an operator reach the dashboard', function () {
    $this->actingAs($this->operator, 'web')
        ->get('/manage')
        ->assertOk();
});

it('blocks renter-type users from the operator panel', function () {
    $renter = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_RENTER,
        'name' => 'Bob Renter',
        'phone' => '+255712000001',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($renter, 'web')
        ->get('/manage')
        ->assertForbidden();
});

it('blocks disabled operators from the panel', function () {
    $this->operator->update(['status' => 'disabled']);

    $this->actingAs($this->operator->fresh(), 'web')
        ->get('/manage')
        ->assertForbidden();
});

it('signs an operator in regardless of the letter case they type their email in', function () {
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(Login::class)
        ->fillForm(['email' => 'Alice@Acme.Local', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('web')->id())->toBe($this->operator->id);
});

it('still rejects a wrong password after normalising the email', function () {
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(Login::class)
        ->fillForm(['email' => 'Alice@Acme.Local', 'password' => 'not-the-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth('web')->check())->toBeFalse();
});
