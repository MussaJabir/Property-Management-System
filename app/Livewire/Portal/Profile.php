<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public ?string $email = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $user = Auth::guard('renter')->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function saveProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        $user = Auth::guard('renter')->user();
        $user->forceFill([
            'name' => $this->name,
            'email' => $this->email,
        ])->save();

        session()->flash('status', __('Profile updated.'));
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'confirmed', Password::min(6)],
        ], attributes: [
            'newPassword' => __('new password'),
        ]);

        $user = Auth::guard('renter')->user();

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', __('Current password is incorrect.'));

            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->newPassword),
            'must_change_password' => false,
        ])->save();

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        session()->flash('status', __('Password changed.'));
    }

    #[Layout('components.layouts.portal', ['authenticated' => true])]
    public function render(): View
    {
        return view('livewire.portal.profile', [
            'user' => Auth::guard('renter')->user(),
        ]);
    }
}
