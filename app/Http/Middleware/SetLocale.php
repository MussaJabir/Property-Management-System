<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the request locale from (in order of precedence):
 *   1. authenticated user's `locale` column (when User model has it — Phase 1+)
 *   2. session('locale') — set by the LocaleSwitcher Livewire component
 *   3. config('app.locale') default ('en')
 *
 * Supported locales come from config('app.supported_locales'), defaulting to
 * ['en', 'sw']. Any value not in that list is ignored.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['en', 'sw']);

        $locale = null;

        if ($request->user() && property_exists($request->user(), 'locale') && in_array($request->user()->locale, $supported, true)) {
            $locale = $request->user()->locale;
        }

        if (! $locale && in_array($request->session()->get('locale'), $supported, true)) {
            $locale = $request->session()->get('locale');
        }

        if ($locale) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
