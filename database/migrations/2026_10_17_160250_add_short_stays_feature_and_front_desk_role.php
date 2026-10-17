<?php

use App\Authorization\OperatorPermissions;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Short stays (Phase 3): the `short_stays` plan feature on Pro and
 * Enterprise, the front desk permissions, and a Receptionist role.
 *
 * Existing roles only gain the new permissions, so any changes a client made
 * on Roles & Permissions are kept.
 */
return new class extends Migration
{
    private const PLANS = ['pro', 'enterprise'];

    private const GRANTS = [
        'owner' => ['frontdesk.view', 'frontdesk.manage'],
        'manager' => ['frontdesk.view', 'frontdesk.manage'],
        'accountant' => ['frontdesk.view'],
    ];

    public function up(): void
    {
        foreach (self::PLANS as $slug) {
            $features = json_decode((string) DB::table('plans')->where('slug', $slug)->value('features'), true) ?: null;

            if (is_array($features) && ! in_array('short_stays', $features, true)) {
                $features[] = 'short_stays';
                DB::table('plans')->where('slug', $slug)->update(['features' => json_encode($features), 'updated_at' => now()]);
            }
        }

        $registrar = app(PermissionRegistrar::class);

        foreach (OperatorPermissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $registrar->forgetCachedPermissions();

        Client::query()->each(function (Client $client) use ($registrar): void {
            $registrar->setPermissionsTeamId($client->getKey());

            foreach (self::GRANTS as $roleName => $permissions) {
                Role::query()
                    ->where('name', $roleName)->where('guard_name', 'web')->where('tenant_id', $client->getKey())
                    ->first()
                    ?->givePermissionTo($permissions);
            }

            Role::firstOrCreate([
                'name' => 'receptionist',
                'guard_name' => 'web',
                'tenant_id' => $client->getKey(),
            ])->syncPermissions(OperatorPermissions::forRole('receptionist'));
        });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::PLANS as $slug) {
            $features = json_decode((string) DB::table('plans')->where('slug', $slug)->value('features'), true);

            if (is_array($features)) {
                DB::table('plans')->where('slug', $slug)->update([
                    'features' => json_encode(array_values(array_diff($features, ['short_stays']))),
                ]);
            }
        }

        Role::query()->where('name', 'receptionist')->where('guard_name', 'web')->delete();
        Permission::query()->whereIn('name', ['frontdesk.view', 'frontdesk.manage'])->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
