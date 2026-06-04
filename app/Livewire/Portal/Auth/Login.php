<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Login extends Component
{
    /** Failed sign-in attempts allowed before the throttle kicks in. */
    private const MAX_ATTEMPTS = 5;

    /** How long (seconds) a failed attempt counts toward the limit. */
    private const DECAY_SECONDS = 60;

    public string $phone = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * Tenant slug captured on the initial (tenancy-initialized) page load.
     * Livewire AJAX updates hit a central /livewire route where path-based
     * tenancy isn't re-resolved, so tenant() is null inside submit().
     */
    #[Locked]
    public string $clientSlug = '';

    public function mount(): void
    {
        $this->clientSlug = tenant()?->slug ?? '';
    }

    public function submit(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->addError('phone', __('Too many attempts. Please try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($throttleKey),
            ]));

            return;
        }

        $normalizedPhone = $this->normalizePhone($this->phone);

        $user = User::query()
            ->where('tenant_id', $this->clientSlug)
            ->where('type', User::TYPE_RENTER)
            ->where('status', User::STATUS_ACTIVE)
            ->whereIn('phone', array_unique([$this->phone, $normalizedPhone]))
            ->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            $this->addError('phone', __('We could not find an account with those details.'));

            return;
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('renter')->login($user, $this->remember);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->save();

        session()->regenerate();

        $this->redirect('/'.$this->clientSlug.'/portal', navigate: false);
    }

    /**
     * Per-client, per-phone, per-IP throttle key so brute-forcing one renter's
     * portal login can't be parallelised across accounts or IPs.
     */
    private function throttleKey(): string
    {
        return 'portal-login:'
            .($this->clientSlug ?: 'none').':'
            .Str::transliterate(Str::lower($this->phone)).':'
            .request()->ip();
    }

    /**
     * Accept "0712345678" (Tanzanian convention) and convert to "+255712345678"
     * so we can match against the stored E.164.
     */
    private function normalizePhone(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+255'.substr($digits, 1);
        }

        if (str_starts_with($digits, '255')) {
            return '+'.$digits;
        }

        return $input;
    }

    #[Layout('components.layouts.portal')]
    public function render()
    {
        return view('livewire.portal.auth.login');
    }
}
