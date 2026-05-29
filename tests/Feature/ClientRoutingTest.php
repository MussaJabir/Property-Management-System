<?php

use App\Models\Client;

it('returns the friendly client-not-found page for an unknown slug', function () {
    $this->get('/no-such-client')
        ->assertStatus(404)
        ->assertSee('Workspace not found')
        ->assertSee('no-such-client');
});

it('resolves a real client via path-based identification', function () {
    Client::create([
        'slug' => 'route-demo',
        'name' => 'Route Demo',
        'status' => 'active',
    ]);

    $this->get('/route-demo')
        ->assertOk()
        ->assertSee('Route Demo');
});
