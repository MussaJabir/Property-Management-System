<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When an operator user signs in with `must_change_password=true` (set by
 * OperatorOwnerProvisioner on auto-issued credentials), park them on the
 * Filament profile page until they save a new password.
 *
 * Allowed-through routes: the profile page itself, logout, livewire AJAX
 * (the profile page renders via Livewire), and Filament asset endpoints.
 */
class ForceOperatorPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        Notification::make()
            ->title('Set a new password')
            ->body('Please choose a new password before continuing.')
            ->warning()
            ->send();

        return redirect()->to('/manage/profile');
    }

    protected function isAllowed(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        // Profile + logout + Filament/Livewire infrastructure.
        $allowed = ['manage/profile', 'manage/logout'];
        foreach ($allowed as $a) {
            if ($path === $a) {
                return true;
            }
        }

        return str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'filament/')
            || str_starts_with($path, 'flux/');
    }
}
