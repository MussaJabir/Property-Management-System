<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base policy for operator-panel resources. Each concrete policy declares a
 * permission $domain; Filament discovers these by the App\Policies\{Model}Policy
 * convention and uses them for every resource action.
 *
 * `manage` implies `view`. Read actions need `{domain}.view` OR `{domain}.manage`;
 * write actions need `{domain}.manage`.
 */
abstract class OperatorResourcePolicy
{
    /** Permission domain, e.g. 'inventory'. */
    protected string $domain;

    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->canManage($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->canManage($user);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->canManage($user);
    }

    protected function canView(User $user): bool
    {
        return $this->check($user, 'view') || $this->check($user, 'manage');
    }

    protected function canManage(User $user): bool
    {
        return $this->check($user, 'manage');
    }

    protected function check(User $user, string $action): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        // Defensive: ensure Spatie's team scope matches the user's client before
        // the permission check (mirrors WorkspaceSettings::canAccess).
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $user->can($this->domain.'.'.$action);
    }
}
