<?php

declare(strict_types=1);

use App\Authorization\OperatorPermissions;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** A read-only Auditor role in every workspace (new ones get it from ClientObserver). */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        Client::query()->each(function (Client $client) use ($registrar): void {
            $registrar->setPermissionsTeamId($client->getKey());
            Role::firstOrCreate([
                'name' => 'auditor',
                'guard_name' => 'web',
                'tenant_id' => $client->getKey(),
            ])->syncPermissions(OperatorPermissions::forRole('auditor'));
        });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'auditor')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
