<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Pages;

use App\Filament\Operator\Resources\Operators\OperatorResource;
use App\Models\Client;
use App\Models\User;
use App\Services\Admin\OperatorProvisioner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOperator extends CreateRecord
{
    protected static string $resource = OperatorResource::class;

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

        $user = app(OperatorProvisioner::class)->provision(
            $client,
            (string) $data['name'],
            (string) $data['email'],
            (string) ($data['role'] ?? 'manager'),
        );

        abort_unless($user !== null, 422);

        return $user;
    }
}
