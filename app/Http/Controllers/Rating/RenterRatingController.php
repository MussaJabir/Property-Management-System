<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rating;

use App\Models\Lease;
use App\Models\Rating;
use App\Models\Renter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Renter portal: "How are we doing?" — one rating per lease, which the
 * renter can change any time.
 */
class RenterRatingController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = Auth::guard('renter')->user();
        $renter = $user instanceof User ? $user->renter : null;

        $lease = $renter instanceof Renter
            ? $renter->leases()->where('status', Lease::STATUS_ACTIVE)->latest('start_date')->first()
            : null;

        abort_if($lease === null, 404);

        Rating::updateOrCreate(
            ['lease_id' => $lease->getKey()],
            ['source' => Rating::SOURCE_RENTER, 'score' => (int) $data['score'], 'comment' => $data['comment'] ?? null],
        );

        return redirect()->route('portal.dashboard', ['tenant' => tenant('id')])
            ->with('status', __('Thank you for your rating.'));
    }
}
