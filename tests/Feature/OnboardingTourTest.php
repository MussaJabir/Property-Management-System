<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create([
        'slug' => 'tourco',
        'name' => 'Tour Co',
        'status' => 'active',
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);

    $this->operator = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Olivia Operator',
        'email' => 'olivia@tourco.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->operator->assignRole('owner');
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('reports that a fresh user still needs onboarding, and not after it is stamped', function () {
    expect($this->operator->needsOnboarding())->toBeTrue();

    $this->operator->forceFill(['onboarding_completed_at' => now()])->save();

    expect($this->operator->fresh()->needsOnboarding())->toBeFalse();
});

it('auto-starts the operator tour on first login', function () {
    $this->withoutVite();

    $this->actingAs($this->operator, 'web')
        ->get('/manage')
        ->assertOk()
        ->assertSee('window.pmsOnboarding', false)
        ->assertSee('"autostart":true', false);
});

it('does not auto-start the operator tour once onboarding is complete', function () {
    $this->withoutVite();
    $this->operator->forceFill(['onboarding_completed_at' => now()])->save();

    $this->actingAs($this->operator->fresh(), 'web')
        ->get('/manage')
        ->assertOk()
        // Config is still present (so "Replay tour" works) but won't auto-start.
        ->assertSee('window.pmsOnboarding', false)
        ->assertSee('"autostart":false', false);
});

it('stamps onboarding completion via the operator endpoint', function () {
    expect($this->operator->onboarding_completed_at)->toBeNull();

    $this->actingAs($this->operator, 'web')
        ->post('/manage/onboarding/complete')
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($this->operator->fresh()->onboarding_completed_at)->not->toBeNull();
});

it('does not stamp anyone when the operator endpoint is hit by a guest', function () {
    $this->post('/manage/onboarding/complete')->assertOk();

    // No authenticated web user → nothing stamped.
    expect($this->operator->fresh()->onboarding_completed_at)->toBeNull();
});

it('auto-starts the renter portal tour on first login', function () {
    $this->withoutVite();
    Tenancy::initialize($this->client);

    $renterUser = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_RENTER,
        'name' => 'Rashid Renter',
        'email' => 'rashid@tourco.local',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($renterUser, 'renter')
        ->get('/'.$this->client->slug.'/portal')
        ->assertOk()
        ->assertSee('window.pmsOnboarding', false)
        ->assertSee('"autostart":true', false);
});

it('stamps onboarding completion via the renter portal endpoint', function () {
    Tenancy::initialize($this->client);

    $renterUser = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_RENTER,
        'name' => 'Rashid Renter',
        'email' => 'rashid2@tourco.local',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($renterUser, 'renter')
        ->post('/'.$this->client->slug.'/portal/onboarding/complete')
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($renterUser->fresh()->onboarding_completed_at)->not->toBeNull();
});
