<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Single source of truth for operator-panel RBAC.
 *
 * Permissions are global (guard `web`); roles are per-client (stancl tenant_id
 * is Spatie's team key). Each domain has a `.view` (read-only) and `.manage`
 * (create/update/delete) permission. `manage` implies `view` at the policy
 * layer — see OperatorResourcePolicy.
 *
 * Role → access (signed off with the client):
 *   owner            full everything (+ workspace settings, gated by role)
 *   manager          full operations across every domain (no settings)
 *   accountant       billing + expenses + reports (manage); inventory,
 *                    tenancy, maintenance (view only)
 *   maintenance-staff maintenance (manage); inventory, tenancy, expenses (view)
 */
final class OperatorPermissions
{
    /** Domains that expose both a `.view` and a `.manage` permission. */
    public const DOMAINS = ['inventory', 'tenancy', 'billing', 'expenses', 'maintenance', 'cms'];

    /**
     * Every operator permission name (guard `web`).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::DOMAINS as $domain) {
            $permissions[] = "{$domain}.view";
            $permissions[] = "{$domain}.manage";
        }

        $permissions[] = 'reports.view';

        return $permissions;
    }

    /**
     * Permissions granted to a given role name.
     *
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        return match ($role) {
            // owner and manager differ only in workspace-settings access, which
            // is gated by role (hasRole('owner')) rather than a permission.
            'owner', 'manager' => self::all(),
            'accountant' => [
                'inventory.view',
                'tenancy.view',
                'billing.view', 'billing.manage',
                'expenses.view', 'expenses.manage',
                'maintenance.view',
                'reports.view',
            ],
            'maintenance-staff' => [
                'inventory.view',
                'tenancy.view',
                'expenses.view',
                'maintenance.view', 'maintenance.manage',
            ],
            default => [],
        };
    }
}
