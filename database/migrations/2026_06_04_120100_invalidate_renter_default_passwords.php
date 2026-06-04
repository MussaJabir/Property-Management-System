<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SECURITY backfill.
 *
 * Earlier renter portal accounts were issued a password derived from the last
 * six digits of the renter's phone number — which is also their login
 * username. Anyone who knew the phone number knew the password, and the
 * "change on first login" promise was never enforced for renters.
 *
 * Any renter still holding that default (must_change_password = true) is
 * vulnerable. Invalidate the password and park the account in
 * `pending_activation` so it must be re-activated through the new one-time
 * link. Renters who already chose their own password
 * (must_change_password = false) are left untouched.
 *
 * On a fresh database this is a no-op (no renter rows exist yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->where('type', User::TYPE_RENTER)
            ->where('must_change_password', true)
            ->chunkById(200, function (Collection $users): void {
                /** @var User $user */
                foreach ($users as $user) {
                    $user->forceFill([
                        'password' => Hash::make(Str::random(40)),
                        'status' => User::STATUS_PENDING_ACTIVATION,
                        'must_change_password' => false,
                        'activation_token' => null,
                        'activation_token_expires_at' => null,
                    ])->save();
                }
            });
    }

    public function down(): void
    {
        // No-op: the weak default passwords cannot (and should not) be restored.
    }
};
