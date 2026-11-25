<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TwoStepLogin;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Sends someone to set up 2-step login only when their workspace requires it
 * for them (owners and managers, when switched on). Filament's own check
 * would send everyone.
 */
class EnsureTwoStepLoginWhenRequired extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! TwoStepLogin::requiredFor(Filament::auth()->user())) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
