<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = $request->input('locale');

        if (! in_array($locale, ['en', 'sw'], true)) {
            return back();
        }

        $user = Auth::guard('renter')->user();

        if ($user) {
            $user->forceFill(['locale' => $locale])->save();
        }

        session()->put('locale', $locale);
        app()->setLocale($locale);

        return back();
    }
}
