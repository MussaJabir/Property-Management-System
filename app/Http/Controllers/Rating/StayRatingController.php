<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rating;

use App\Models\Booking;
use App\Models\Rating;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Public, no-login rating page for a guest's stay. Reached only through a
 * signed link the front desk shares at check-out (WhatsApp), valid for
 * 30 days. The guest can change their rating while the link lasts.
 */
class StayRatingController
{
    public const VALID_DAYS = 30;

    /** The link the front desk shares with the guest. */
    public static function linkFor(Booking $booking): string
    {
        return URL::temporarySignedRoute(
            'rate.stay',
            Carbon::now()->addDays(self::VALID_DAYS),
            ['tenant' => (string) $booking->tenant_id, 'booking' => $booking->getKey()],
        );
    }

    // Path tenancy removes {tenant} from the route parameters.
    public function show(string $booking): View
    {
        $stay = $this->booking($booking);

        return view('rating.stay', [
            'booking' => $stay,
            'rating' => $stay->rating,
            'action' => request()->fullUrl(),
        ]);
    }

    public function store(Request $request, string $booking): RedirectResponse
    {
        $stay = $this->booking($booking);

        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        Rating::updateOrCreate(
            ['booking_id' => $stay->getKey()],
            ['source' => Rating::SOURCE_GUEST, 'score' => (int) $data['score'], 'comment' => $data['comment'] ?? null],
        );

        return redirect()->to($request->fullUrl())->with('rated', true);
    }

    /** Only stays that actually happened can be rated. */
    private function booking(string $id): Booking
    {
        $booking = Booking::query()
            ->whereIn('status', [Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->with(['guest', 'unit.property', 'rating'])
            ->find($id);

        abort_if($booking === null, 404);

        return $booking;
    }
}
