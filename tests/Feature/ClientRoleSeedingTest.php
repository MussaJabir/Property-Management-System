<?php

use App\Models\Client;
use Spatie\Permission\Models\Role;

it('auto-seeds six default roles when a client is created', function () {
    $client = Client::create([
        'slug' => 'rolecheck',
        'name' => 'Role Check Ltd',
        'status' => 'active',
    ]);

    $roleNames = Role::where('tenant_id', $client->id)
        ->where('guard_name', 'web')
        ->pluck('name')
        ->all();

    expect($roleNames)
        ->toContain('owner', 'manager', 'accountant', 'maintenance-staff', 'receptionist', 'auditor')
        ->and(count($roleNames))->toBe(6);
});

it('keeps roles scoped per client (no cross-client leakage)', function () {
    $clientA = Client::create([
        'slug' => 'tenant-a',
        'name' => 'Tenant A',
        'status' => 'active',
    ]);

    $clientB = Client::create([
        'slug' => 'tenant-b',
        'name' => 'Tenant B',
        'status' => 'active',
    ]);

    expect(Role::where('tenant_id', $clientA->id)->count())->toBe(6);
    expect(Role::where('tenant_id', $clientB->id)->count())->toBe(6);
});
