<?php

use App\Models\Client;
use App\Models\User;
use App\Notifications\ClientStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'acme', 'name' => 'Acme Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->operator = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Acme Owner',
        'email' => 'owner@acme.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->operator->assignRole('owner');
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('lets an operator into the panel while the client is active', function () {
    $this->actingAs($this->operator, 'web')->get('/manage')->assertOk();
});

it('blocks the operator panel when the client is suspended', function () {
    $this->client->update(['status' => 'suspended']);

    $this->actingAs($this->operator, 'web')
        ->get('/manage')
        ->assertStatus(403)
        ->assertSee('suspended', false);
});

it('blocks the public site when the client is suspended', function () {
    $this->client->update(['status' => 'suspended']);

    $this->get('/acme')
        ->assertStatus(403)
        ->assertSee('suspended', false);
});

it('emails the operator when the client is suspended and reactivated', function () {
    Notification::fake();

    $this->client->update(['status' => 'suspended']);
    Notification::assertSentTo(
        $this->operator,
        ClientStatusChangedNotification::class,
        fn ($n) => $n->suspended === true,
    );

    $this->client->update(['status' => 'active']);
    Notification::assertSentTo(
        $this->operator,
        ClientStatusChangedNotification::class,
        fn ($n) => $n->suspended === false,
    );
});

it('does not notify on unrelated client updates', function () {
    Notification::fake();

    $this->client->update(['name' => 'Acme Renamed']);

    Notification::assertNothingSent();
});
