<?php

use App\Models\Tenant;

it('returns the friendly tenant-not-found page for an unknown slug', function () {
    $this->get('/no-such-tenant')
        ->assertStatus(404)
        ->assertSee('Workspace not found')
        ->assertSee('no-such-tenant');
});

it('resolves a real tenant via path-based identification', function () {
    Tenant::create([
        'slug' => 'route-demo',
        'name' => 'Route Demo',
        'status' => 'active',
    ]);

    $this->get('/route-demo')
        ->assertOk()
        ->assertSee('route-demo');
});
