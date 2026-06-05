<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\SuperAdminUser;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('fences the admin and operator panels by user type', function () {
    $client = Client::create(['slug' => 'panelco', 'name' => 'Panel Co.', 'status' => 'active']);
    tenancy()->initialize($client);

    $operator = User::create([
        'tenant_id' => $client->getKey(),
        'type' => User::TYPE_OPERATOR,
        'name' => 'Op',
        'email' => 'op@panelco.test',
        'password' => 'secret-password',
        'status' => User::STATUS_ACTIVE,
    ]);

    $superAdmin = SuperAdminUser::create([
        'name' => 'Root',
        'email' => 'root@pms.test',
        'password' => 'secret-password',
    ]);

    $adminPanel = Filament::getPanel('admin');
    $operatorPanel = Filament::getPanel('operator');

    // Operators may only enter the operator panel — never the super-admin panel
    // (which owns Clients / Plans / Subscriptions).
    expect($operator->canAccessPanel($operatorPanel))->toBeTrue()
        ->and($operator->canAccessPanel($adminPanel))->toBeFalse();

    // Super admins may only enter the admin panel — never a tenant workspace.
    expect($superAdmin->canAccessPanel($adminPanel))->toBeTrue()
        ->and($superAdmin->canAccessPanel($operatorPanel))->toBeFalse();
});

it('keeps a non-active operator out of the operator panel', function () {
    $client = Client::create(['slug' => 'panelco2', 'name' => 'Panel Co. 2', 'status' => 'active']);
    tenancy()->initialize($client);

    $operator = User::create([
        'tenant_id' => $client->getKey(),
        'type' => User::TYPE_OPERATOR,
        'name' => 'Disabled Op',
        'email' => 'disabled@panelco2.test',
        'password' => 'secret-password',
        'status' => User::STATUS_DISABLED,
    ]);

    expect($operator->canAccessPanel(Filament::getPanel('operator')))->toBeFalse();
});
