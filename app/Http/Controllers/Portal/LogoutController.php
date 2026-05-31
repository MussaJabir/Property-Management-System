<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $client = tenant();

        Auth::guard('renter')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/'.($client?->slug ?? '').'/portal/login');
    }
}
