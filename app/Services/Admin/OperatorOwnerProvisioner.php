<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Client;
use App\Models\User;
use App\Notifications\OperatorCredentialsIssuedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Provisions the first operator (owner) user when a super admin creates
 * a new Client. Mirror of Phase 8's RenterPortalAccountProvisioner.
 *
 * Idempotent: if the email already exists for this tenant, returns that
 * user instead of creating a duplicate. Generates a strong random temp
 * password; the operator must change it on first sign-in via the
 * `must_change_password` flag.
 */
class OperatorOwnerProvisioner
{
    public function provisionFor(Client $client, string $name, string $email, ?string $phone = null): ?User
    {
        $email = trim(Str::lower($email));

        if ($email === '' || $name === '') {
            return null;
        }

        // Idempotency: don't double-create if an operator with this email
        // already exists for the client.
        $existing = User::query()
            ->where('tenant_id', $client->id)
            ->where('type', User::TYPE_OPERATOR)
            ->where('email', $email)
            ->first();

        if ($existing) {
            return $existing;
        }

        $tempPassword = $this->generateTemporaryPassword();

        return DB::transaction(function () use ($client, $name, $email, $phone, $tempPassword): User {
            $user = User::create([
                'tenant_id' => $client->id,
                'type' => User::TYPE_OPERATOR,
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: null,
                'password' => Hash::make($tempPassword),
                'status' => 'active',
                'locale' => 'en',
                'must_change_password' => true,
            ]);

            // Assign the owner role under this client's permission team.
            app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);
            $user->assignRole('owner');

            try {
                $user->notify(new OperatorCredentialsIssuedNotification($tempPassword));
            } catch (Throwable $e) {
                Log::warning('Operator credentials email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $user;
        });
    }

    /**
     * 10-char alphanumeric password — short enough to type, long enough to
     * resist guessing during the small window before the user changes it.
     */
    public function generateTemporaryPassword(): string
    {
        return Str::random(10);
    }
}
