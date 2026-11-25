<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Whether a workspace requires 2-step login (authenticator app) for its
 * owners and managers. Off by default; Settings → Security turns it on.
 */
final class TwoStepLogin
{
    public const SETTING = 'require_two_step_login';

    public const ROLES = ['owner', 'manager'];

    public static function requiredByWorkspace(?Client $client): bool
    {
        return (bool) ($client?->settings[self::SETTING] ?? false);
    }

    public static function requiredFor(mixed $user): bool
    {
        if (! $user instanceof User || ! $user->tenant_id || $user->type !== User::TYPE_OPERATOR) {
            return false;
        }

        if (! self::requiredByWorkspace(Client::find($user->tenant_id))) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $user->hasAnyRole(self::ROLES);
    }
}
