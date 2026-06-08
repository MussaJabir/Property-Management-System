<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Client;
use App\Models\Renter;
use App\Models\User;
use App\Notifications\PortalActivationNotification;
use App\Services\Sms\BeemSmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Idempotently provisions a renter portal account and drives the activation
 * flow.
 *
 * No password is ever generated or transmitted. The account is created in
 * `pending_activation` with an unusable password; the renter sets their own
 * password through a one-time, expiring activation link (delivered by email
 * when present, or shared by the operator via the "resend activation" action).
 *
 * Replaces the Phase 8 phone-derived default-password scheme, which let anyone
 * who knew a renter's phone number sign in — the phone is also the username.
 */
class RenterPortalAccountProvisioner
{
    /** How long an activation link stays valid, in hours. */
    public const TOKEN_TTL_HOURS = 72;

    /**
     * Create the portal user (if absent) and send an activation invite.
     * Idempotent: an already-linked renter is returned untouched.
     */
    public function provisionFor(Renter $renter): ?User
    {
        if ($renter->user_id) {
            return $renter->user;
        }

        $user = $this->createPortalUser($renter);

        if (! $user) {
            return null;
        }

        $this->sendActivation($user, $renter);

        return $user;
    }

    /**
     * (Re)issue an activation link for a renter and return the URL so the
     * operator can copy/share it. Creates the portal user first if needed,
     * and always invalidates the current password until the link is used.
     *
     * Returns null only when no portal user can exist yet (renter has no
     * phone number — the portal login identifier).
     */
    public function resendActivation(Renter $renter): ?string
    {
        $user = $renter->user;

        if (! $user instanceof User) {
            $user = $this->createPortalUser($renter);

            if (! $user) {
                return null;
            }
        } else {
            $user->forceFill([
                'password' => Hash::make(Str::random(40)),
                'status' => User::STATUS_PENDING_ACTIVATION,
            ])->save();
        }

        return $this->sendActivation($user, $renter);
    }

    /**
     * Create the renter-type User row in `pending_activation` with an unusable
     * password and link it back to the renter. Does not send anything.
     */
    protected function createPortalUser(Renter $renter): ?User
    {
        $phone = (string) $renter->getRawOriginal('phone');

        if ($phone === '') {
            return null;
        }

        return DB::transaction(function () use ($renter, $phone): User {
            $user = User::create([
                'tenant_id' => $renter->tenant_id,
                'type' => User::TYPE_RENTER,
                'name' => $renter->full_name,
                'email' => $this->portalEmailFor($renter),
                'phone' => $phone,
                'password' => Hash::make(Str::random(40)), // unusable until activation
                'locale' => 'en',
                'status' => User::STATUS_PENDING_ACTIVATION,
                'must_change_password' => false,
            ]);

            $renter->user_id = $user->id;
            $renter->save();

            return $user;
        });
    }

    /**
     * Email to stamp on the renter's portal User, or null.
     *
     * The users table enforces a platform-wide unique email, but a renter can
     * legitimately share an address with an operator (or rent in more than one
     * workspace). Renters sign in by phone — the email is only used to deliver
     * the activation invite — so drop it when it's already taken rather than
     * blow up on the unique constraint. The operator can still share the link.
     */
    protected function portalEmailFor(Renter $renter): ?string
    {
        $email = $renter->email;

        if (! $email) {
            return null;
        }

        return User::query()->where('email', $email)->exists() ? null : $email;
    }

    /**
     * Issue a fresh token, build the link, email it (best effort) and return
     * the URL so callers can surface it for manual sharing.
     */
    protected function sendActivation(User $user, Renter $renter): string
    {
        $client = Client::find($renter->tenant_id);
        $url = url('/'.($client?->slug ?? '').'/portal/activate/'.$user->id.'/'.$this->issueToken($user));

        // Deliver the invite straight to the renter's own email — not the portal
        // User's, which may be null when the address collides with another user
        // (platform-wide unique email). Routed on-demand so a nulled User email
        // can't swallow it. Operators can still copy the returned link to share.
        if ($renter->email) {
            try {
                Notification::route('mail', $renter->email)->notify(
                    new PortalActivationNotification(
                        $url,
                        $renter->full_name,
                        $client?->name ?? 'your landlord',
                    )
                );
            } catch (Throwable $e) {
                Log::warning('Portal activation email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also deliver the link by SMS (Beem) — covers renters with no email, or
        // an email that collides with another user. Best-effort; never throws.
        $phone = (string) $renter->getRawOriginal('phone');
        if ($phone !== '') {
            app(BeemSmsSender::class)->send(
                $phone,
                (string) __('Activate your :app account: :url', ['app' => $client?->name ?? 'PMS', 'url' => $url]),
            );
        }

        return $url;
    }

    /**
     * Store the hash of a new high-entropy token (and its expiry) on the user,
     * returning the raw token to embed in the link.
     */
    protected function issueToken(User $user): string
    {
        $rawToken = Str::random(64);

        $user->forceFill([
            'activation_token' => Hash::make($rawToken),
            'activation_token_expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
        ])->save();

        return $rawToken;
    }
}
