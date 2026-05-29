<?php

namespace App\Observers;

use App\Models\Client;
use Spatie\Permission\Models\Role;

/**
 * Seed the four default roles whenever a new Client is created, so the
 * first operator user can be assigned a role without manual SQL.
 *
 * Roles are scoped to the client via Spatie's teams feature, where
 * team_id == tenant_id == client.id (configured in config/permission.php).
 */
class ClientObserver
{
    public const DEFAULT_ROLES = [
        'owner',
        'manager',
        'accountant',
        'maintenance-staff',
    ];

    public function created(Client $client): void
    {
        foreach (self::DEFAULT_ROLES as $name) {
            Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
                'tenant_id' => $client->id,
            ]);
        }
    }
}
