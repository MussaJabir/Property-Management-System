<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps the onboarding tour as completed for the current user. Called by the
 * tour JS when the user finishes or skips it, so it never auto-starts again.
 *
 * Operators authenticate on the `web` guard, renters on the `renter` guard —
 * both resolve to App\Models\User, which carries onboarding_completed_at.
 */
class OnboardingController extends Controller
{
    public function complete(string $guard): JsonResponse
    {
        // Only the two guards that front a dashboard tour are accepted.
        $user = in_array($guard, ['web', 'renter'], true)
            ? Auth::guard($guard)->user()
            : null;

        if ($user && $user->onboarding_completed_at === null) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return response()->json(['status' => 'ok']);
    }
}
