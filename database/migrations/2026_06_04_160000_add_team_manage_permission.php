<?php

use App\Authorization\OperatorPermissions;
use App\Models\Client;
use App\Observers\ClientObserver;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 4c: add the `team.manage` permission (gates the Team management UI)
 * and re-sync every existing client's roles so owner + manager pick it up.
 * Re-syncing is idempotent and leaves accountant / maintenance-staff unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        foreach (OperatorPermissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $registrar->forgetCachedPermissions();

        Client::query()->each(function (Client $client) use ($registrar): void {
            $registrar->setPermissionsTeamId($client->getKey());

            foreach (ClientObserver::DEFAULT_ROLES as $roleName) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $client->getKey(),
                ]);

                $role->syncPermissions(OperatorPermissions::forRole($roleName));
            }
        });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'team.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
