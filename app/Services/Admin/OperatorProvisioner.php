<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Client;
use App\Models\User;
use App\Notifications\OperatorActivationNotification;
use App\Services\Sms\BeemSmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Provisions an operator (staff) account and drives the activation-link flow —
 * no password is ever generated or emailed. The member sets their own password
 * via a one-time, expiring link (mirrors the renter flow from Phase 1).
 *
 * Used for the first owner on client creation and for staff invited from the
 * operator Team page. Operators sign in by email, so the address is required
 * and unique; the invite is delivered to it.
 */
class OperatorProvisioner
{
    /** How long an activation link stays valid, in hours. */
    public const TOKEN_TTL_HOURS = 72;

    /**
     * Create an operator in `pending_activation` with the given role and send
     * an activation invite. Idempotent: an existing operator with this email
     * for the client is returned untouched.
     */
    public function provision(Client $client, string $name, string $email, string $role, ?string $phone = null): ?User
    {
        $email = trim(Str::lower($email));
        $name = trim($name);

        if ($email === '' || $name === '') {
            return null;
        }

        $existing = User::query()
            ->where('tenant_id', $client->id)
            ->where('type', User::TYPE_OPERATOR)
            ->where('email', $email)
            ->first();

        if ($existing) {
            return $existing;
        }

        $user = DB::transaction(function () use ($client, $name, $email, $phone, $role): User {
            $user = User::create([
                'tenant_id' => $client->id,
                'type' => User::TYPE_OPERATOR,
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: null,
                'password' => Hash::make(Str::random(40)), // unusable until activation
                'status' => User::STATUS_PENDING_ACTIVATION,
                'locale' => 'en',
                'must_change_password' => false,
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);
            $user->assignRole($role);

            return $user;
        });

        $this->sendActivation($user);

        return $user;
    }

    /**
     * (Re)issue an activation link for an operator and return the URL so it can
     * be copied/shared. Invalidates any current password until the link is used.
     */
    public function resend(User $user): string
    {
        $user->forceFill([
            'status' => User::STATUS_PENDING_ACTIVATION,
            'password' => Hash::make(Str::random(40)),
        ])->save();

        return $this->sendActivation($user);
    }

    protected function sendActivation(User $user): string
    {
        $url = $this->buildActivationUrl($user, $this->issueToken($user));

        try {
            $user->notify(new OperatorActivationNotification($url));
        } catch (Throwable $e) {
            Log::warning('Operator activation email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Also send the link by SMS (Beem) when a phone is on file. Best-effort.
        if ($user->phone) {
            app(BeemSmsSender::class)->send(
                (string) $user->phone,
                (string) __('Activate your account: :url', ['url' => $url]),
            );
        }

        return $url;
    }

    protected function issueToken(User $user): string
    {
        $rawToken = Str::random(64);

        $user->forceFill([
            'activation_token' => Hash::make($rawToken),
            'activation_token_expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
        ])->save();

        return $rawToken;
    }

    protected function buildActivationUrl(User $user, string $rawToken): string
    {
        return url('/staff/activate/'.$user->id.'/'.$rawToken);
    }
}
