<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Operators\Pages;

use App\Filament\Operator\Resources\Operators\OperatorResource;
use App\Models\User;
use App\Notifications\OperatorCredentialsIssuedNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class CreateOperator extends CreateRecord
{
    protected static string $resource = OperatorResource::class;

    /**
     * Invite an operator: create the user with a temporary password, force a
     * change on first sign-in, assign the chosen role, and email credentials.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $clientId = (string) $actor->tenant_id;
        $role = $data['role'] ?? 'manager';
        $tempPassword = Str::random(10);

        $user = User::create([
            'tenant_id' => $clientId,
            'type' => User::TYPE_OPERATOR,
            'name' => $data['name'],
            'email' => Str::lower(trim((string) $data['email'])),
            'password' => Hash::make($tempPassword),
            'status' => 'active',
            'locale' => 'en',
            'must_change_password' => true,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($clientId);
        $user->assignRole($role);

        try {
            $user->notify(new OperatorCredentialsIssuedNotification($tempPassword));
        } catch (Throwable $e) {
            Log::warning('Operator invite email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }
}
