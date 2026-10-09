<?php

use App\Models\Client;
use Illuminate\Support\Facades\Exceptions;

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

it('does not report an unknown first URL segment as an application error', function () {
    Exceptions::fake();

    $this->get('/sitemap.xml')->assertStatus(404);

    Exceptions::assertNothingReported();
});
