<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Central operator activation: a staff member follows the one-time link from
 * their invite, sets their own password, and is signed in to the operator
 * panel. The link carries the user id and a high-entropy token whose hash is
 * stored on the user row; it's only valid while the account is
 * pending_activation, the token matches, and it hasn't expired.
 */
class OperatorActivationController
{
    public function show(string $user, string $token): View
    {
        return view('operator.activate', [
            'valid' => $this->resolve($user, $token) instanceof User,
            'userId' => $user,
            'token' => $token,
        ]);
    }

    public function store(Request $request, string $user, string $token): RedirectResponse
    {
        $operator = $this->resolve($user, $token);

        if (! $operator instanceof User) {
            return redirect()
                ->route('operator.activate', ['user' => $user, 'token' => $token])
                ->withErrors(['password' => __('This activation link is invalid or has expired.')]);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $operator->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
            'activation_token' => null,
            'activation_token_expires_at' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Auth::guard('web')->login($operator);
        $request->session()->regenerate();

        return redirect()->to('/manage');
    }

    protected function resolve(string $userId, string $token): ?User
    {
        $user = User::find($userId);

        if (! $user
            || ! $user->isOperator()
            || ! $user->isPendingActivation()
            || $user->activation_token === null
            || $user->activation_token_expires_at === null
            || $user->activation_token_expires_at->isPast()
            || ! Hash::check($token, $user->activation_token)
        ) {
            return null;
        }

        return $user;
    }
}
