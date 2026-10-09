<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Pages;

use App\Filament\Operator\Resources\Operators\OperatorResource;
use App\Filament\Support\InviteNotice;
use App\Models\Client;
use App\Models\User;
use App\Services\Admin\OperatorInvite;
use App\Services\Admin\OperatorProvisioner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOperator extends CreateRecord
{
    protected static string $resource = OperatorResource::class;

    protected ?OperatorInvite $invite = null;

    /**
     * Invite an operator: provisions a pending_activation account with the
     * chosen role and emails a one-time activation link (no password). The
     * member sets their own password to activate.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $actor->tenant_id, 403);

        $client = Client::find($actor->tenant_id);
        abort_unless($client !== null, 403);

        $provisioner = app(OperatorProvisioner::class);

        $user = $provisioner->provision(
            $client,
            (string) $data['name'],
            (string) $data['email'],
            (string) ($data['role'] ?? 'manager'),
        );

        abort_unless($user !== null, 422);

        $this->invite = $provisioner->lastInvite();

        return $user;
    }

    protected function afterCreate(): void
    {
        if ($this->invite !== null && $this->record instanceof User) {
            InviteNotice::send($this->record, $this->invite);
        }
    }
}
