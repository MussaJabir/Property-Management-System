<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Renter;
use App\Models\User;
use App\Notifications\PortalCredentialsIssuedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotently provisions a renter portal user for a Renter.
 *
 * Called from Lease::activate(). If the renter already has a linked user
 * (renter.user_id) we leave it alone. Otherwise we create a renter-type User
 * row, link it back to the renter, and dispatch a notification with the
 * temporary credentials.
 *
 * Default password: last 6 digits of the E.164 phone (no spaces). The user is
 * forced to change it on first login via the must_change_password flag.
 */
class RenterPortalAccountProvisioner
{
    public function provisionFor(Renter $renter): ?User
    {
        if ($renter->user_id) {
            return $renter->user;
        }

        $phone = (string) $renter->getRawOriginal('phone');

        if ($phone === '') {
            return null;
        }

        $defaultPassword = $this->defaultPasswordFor($phone);

        return DB::transaction(function () use ($renter, $phone, $defaultPassword): User {
            $user = User::create([
                'tenant_id' => $renter->tenant_id,
                'type' => User::TYPE_RENTER,
                'name' => $renter->full_name,
                'email' => $renter->email,
                'phone' => $phone,
                'password' => Hash::make($defaultPassword),
                'locale' => 'en',
                'status' => 'active',
                'must_change_password' => true,
            ]);

            $renter->user_id = $user->id;
            $renter->save();

            try {
                $user->notify(new PortalCredentialsIssuedNotification($defaultPassword));
            } catch (Throwable $e) {
                // Don't fail the lease activation if mail is misconfigured.
                Log::warning('Portal credentials email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $user;
        });
    }

    /**
     * The temporary password we ship to the renter — last 6 digits of phone.
     * Always 6 digits for predictability (zero-padded if phone is too short).
     */
    public function defaultPasswordFor(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_pad(substr($digits, -6), 6, '0', STR_PAD_LEFT);
    }
}
