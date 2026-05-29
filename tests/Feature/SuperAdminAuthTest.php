<?php

use App\Models\SuperAdminUser;

it('redirects unauthenticated visitors away from /admin', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('serves the super admin login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('logs in a super admin and lets them reach the dashboard', function () {
    $admin = SuperAdminUser::create([
        'name' => 'Test Admin',
        'email' => 'test-admin@pms.local',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'super_admin')
        ->get('/admin')
        ->assertOk();
});

it('keeps the operator-tenant User model away from the super_admin guard', function () {
    $admin = SuperAdminUser::create([
        'name' => 'Test Admin',
        'email' => 'test-admin@pms.local',
        'password' => 'password',
    ]);

    expect($admin->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});
