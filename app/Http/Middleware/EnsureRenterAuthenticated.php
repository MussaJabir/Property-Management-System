<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the renter portal. The user must:
 *   1. Be authenticated on the `renter` guard
 *   2. Be of type=renter (defensive — admin can't shortcut into someone's portal)
 *   3. Belong to the currently resolved tenant (stancl/tenancy already verified the slug)
 *   4. Apply locale from the user record
 */
class EnsureRenterAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('renter')->user();
        $client = tenant();

        if (! $user || ! $user->isRenter() || $user->status !== 'active' || ($client && $user->tenant_id !== $client->getKey())) {
            Auth::guard('renter')->logout();
            $request->session()->invalidate();

            return redirect()->to('/'.($client?->slug ?? '').'/portal/login');
        }

        if ($user->locale && in_array($user->locale, ['en', 'sw'], true)) {
            app()->setLocale($user->locale);
        }

        return $next($request);
    }
}
