<?php

use App\Models\Plan;
use App\Models\SuperAdminUser;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->admin = SuperAdminUser::create([
        'name' => 'Test Admin',
        'email' => 'crud-test@pms.local',
        'password' => 'password',
    ]);

    $this->plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan',
        'price_tzs' => 1_000_000,
        'billing_period' => 'monthly',
    ]);
});

it('creates a tenant with custom columns persisted', function () {
    $tenant = Tenant::create([
        'id' => 'tenant-test-1',
        'slug' => 'demo',
        'name' => 'Demo Properties Ltd',
        'contact_email' => 'hello@demo.example',
        'plan_id' => $this->plan->id,
        'status' => 'trial',
    ]);

    expect($tenant->fresh())
        ->slug->toBe('demo')
        ->name->toBe('Demo Properties Ltd')
        ->status->toBe('trial')
        ->plan_id->toBe($this->plan->id);
});

it('exposes the plan relationship from a tenant', function () {
    $tenant = Tenant::create([
        'id' => 'tenant-test-2',
        'slug' => 'kariakoo',
        'name' => 'Kariakoo Heights',
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    expect($tenant->fresh()->plan->name)->toBe('Test Plan');
});

it('suspends and reactivates a tenant', function () {
    $tenant = Tenant::create([
        'id' => 'tenant-test-3',
        'slug' => 'demo3',
        'name' => 'Demo 3',
        'status' => 'active',
    ]);

    $tenant->update(['status' => 'suspended']);
    expect($tenant->fresh()->isSuspended())->toBeTrue();

    $tenant->update(['status' => 'active']);
    expect($tenant->fresh()->isActive())->toBeTrue();
});

it('enforces unique slugs across tenants', function () {
    Tenant::create([
        'id' => 'tenant-a',
        'slug' => 'unique-slug',
        'name' => 'A',
        'status' => 'trial',
    ]);

    expect(fn () => Tenant::create([
        'id' => 'tenant-b',
        'slug' => 'unique-slug',
        'name' => 'B',
        'status' => 'trial',
    ]))->toThrow(QueryException::class);
});

it('soft-deletes tenants', function () {
    $tenant = Tenant::create([
        'id' => 'tenant-soft',
        'slug' => 'soft',
        'name' => 'Soft Delete',
        'status' => 'cancelled',
    ]);

    $tenant->delete();

    expect(Tenant::find('tenant-soft'))->toBeNull();
    expect(Tenant::withTrashed()->find('tenant-soft'))->not->toBeNull();
});
