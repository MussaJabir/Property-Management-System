<?php

use App\Authorization\OperatorPermissions;
use App\Models\Client;
use App\Observers\ClientObserver;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Operator RBAC (Phase 4b): create the global operator permissions and assign
 * each existing client's roles their permission set. New clients are handled by
 * ClientObserver. Roles were previously seeded but had no permissions, so every
 * operator effectively had full access — this enforces the agreed role matrix.
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

        // Backfill existing clients' roles (no-op on a fresh database).
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
        foreach (OperatorPermissions::all() as $name) {
            Permission::query()->where('name', $name)->where('guard_name', 'web')->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
