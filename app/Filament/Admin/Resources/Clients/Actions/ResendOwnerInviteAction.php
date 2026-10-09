<?php

namespace App\Filament\Admin\Resources\Clients\Actions;

use App\Filament\Support\InviteNotice;
use App\Models\Client;
use App\Models\User;
use App\Services\Admin\OperatorProvisioner;
use Filament\Actions\Action;
use Spatie\Permission\PermissionRegistrar;

/**
 * Re-issues the activation invite for a client's owner while they are still
 * pending activation. The owner cannot sign in to their own workspace yet, so
 * the super admin panel is the only place this can be done from.
 */
class ResendOwnerInviteAction
{
    public static function make(): Action
    {
        return Action::make('resendOwnerInvite')
            ->label(__('Resend owner invite'))
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->visible(fn (Client $record): bool => ! $record->trashed() && self::pendingOwner($record) !== null)
            ->requiresConfirmation()
            ->modalHeading(__('Resend owner invite'))
            ->modalDescription(__('Issues a fresh activation link and emails it again. Any previous link stops working.'))
            ->action(function (Client $record): void {
                $owner = self::pendingOwner($record);

                if ($owner === null) {
                    return;
                }

                $provisioner = app(OperatorProvisioner::class);
                $provisioner->resend($owner);

                $invite = $provisioner->lastInvite();

                if ($invite !== null) {
                    InviteNotice::send($owner, $invite);
                }
            });
    }

    protected static function pendingOwner(Client $client): ?User
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();

        // Roles are scoped per client; the admin panel runs with no team set.
        $registrar->setPermissionsTeamId($client->getKey());

        try {
            return User::query()
                ->where('tenant_id', $client->getKey())
                ->where('type', User::TYPE_OPERATOR)
                ->where('status', User::STATUS_PENDING_ACTIVATION)
                ->role('owner')
                ->oldest()
                ->first();
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}
