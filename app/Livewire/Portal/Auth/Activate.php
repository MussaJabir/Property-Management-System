<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Renter account activation: the renter follows a one-time link from the
 * invite, sets their own password, and is signed in. The link carries the
 * user id and a high-entropy token whose hash is stored on the user row.
 *
 * A link is only valid while the account is `pending_activation`, the token
 * matches, and it hasn't expired — so a replayed or stale link is refused.
 */
class Activate extends Component
{
    public string $userId = '';

    public string $token = '';

    public bool $valid = false;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $user, string $token): void
    {
        $this->userId = $user;
        $this->token = $token;
        $this->valid = $this->resolveUser() !== null;
    }

    /**
     * Return the user this link activates, or null if the link is invalid,
     * expired, already used, or belongs to another client.
     */
    protected function resolveUser(): ?User
    {
        $client = tenant();
        $user = User::find($this->userId);

        if (! $user
            || ! $user->isRenter()
            || ($client && $user->tenant_id !== $client->getKey())
            || ! $user->isPendingActivation()
            || $user->activation_token === null
            || $user->activation_token_expires_at === null
            || $user->activation_token_expires_at->isPast()
            || ! Hash::check($this->token, $user->activation_token)
        ) {
            return null;
        }

        return $user;
    }

    public function submit(): void
    {
        $user = $this->resolveUser();

        if (! $user) {
            $this->valid = false;

            return;
        }

        $this->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], attributes: ['password' => __('password')]);

        $user->forceFill([
            'password' => Hash::make($this->password),
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
            'activation_token' => null,
            'activation_token_expires_at' => null,
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        Auth::guard('renter')->login($user);
        session()->regenerate();

        $client = tenant();
        $this->redirect('/'.($client?->slug ?? '').'/portal', navigate: false);
    }

    #[Layout('components.layouts.portal')]
    public function render()
    {
        return view('livewire.portal.auth.activate');
    }
}
