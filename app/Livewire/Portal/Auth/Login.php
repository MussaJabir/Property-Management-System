<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Login extends Component
{
    public string $phone = '';

    public string $password = '';

    public bool $remember = false;

    public function submit(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $client = tenant();

        $normalizedPhone = $this->normalizePhone($this->phone);

        $user = User::query()
            ->where('tenant_id', $client?->getKey())
            ->where('type', User::TYPE_RENTER)
            ->where('status', 'active')
            ->whereIn('phone', array_unique([$this->phone, $normalizedPhone]))
            ->first();

        if (! $user || ! Hash::check($this->password, $user->password)) {
            $this->addError('phone', __('We could not find an account with those details.'));

            return;
        }

        Auth::guard('renter')->login($user, $this->remember);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->save();

        session()->regenerate();

        $this->redirect('/'.$client->slug.'/portal', navigate: false);
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
