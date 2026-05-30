<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\ExpenseCategory;
use Spatie\Permission\Models\Role;

/**
 * Seed sensible defaults whenever a new Client is created so the workspace
 * is usable out of the box:
 *
 *   - four default roles (Spatie teams scoped to tenant_id)
 *   - six default expense categories (Repair, Cleaning, Security, …)
 *
 * Tenancy global scopes on ExpenseCategory inject tenant_id automatically
 * because the writes happen inside a tenant context — but we set it
 * explicitly here for robustness (this observer can fire outside a tenant
 * context when a client is created from the super-admin panel).
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

        foreach (ExpenseCategory::DEFAULT_CATEGORIES as $cat) {
            ExpenseCategory::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $client->id, 'name' => $cat['name']],
                ['color' => $cat['color']],
            );
        }
    }
}
