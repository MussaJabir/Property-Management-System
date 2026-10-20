<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rating;

use App\Models\Client;
use App\Models\Lease;
use App\Models\Rating;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Public, no-login review page for a renter's room: most renters never
 * sign in to the portal, so staff send this signed link on WhatsApp from
 * the Leases list. Valid for 30 days; the renter can change their review
 * while it lasts (which hides it from the website until approved again).
 */
class LeaseRatingController
{
    public const VALID_DAYS = 30;

    public static function linkFor(Lease $lease): string
    {
        return URL::temporarySignedRoute(
            'rate.lease',
            Carbon::now()->addDays(self::VALID_DAYS),
            ['tenant' => (string) $lease->tenant_id, 'lease' => $lease->getKey()],
        );
    }

    /** WhatsApp chat with the renter, the review link already in the message. */
    public static function whatsAppUrl(Lease $lease): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $lease->renter?->phone);

        if ($phone === '' || $phone === null) {
            return null;
        }

        $client = Client::find($lease->tenant_id);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode(__('Hello :name, how is your room at :client? Please leave us a short review here: :link', [
            'name' => strtok((string) $lease->renter?->full_name, ' '),
            'client' => $client !== null ? $client->name : '',
            'link' => self::linkFor($lease),
        ]));
    }

    // Path tenancy removes {tenant} from the route parameters.
    public function show(string $lease): View
    {
        $tenancy = $this->lease($lease);

        return view('rating.lease', [
            'lease' => $tenancy,
            'rating' => $tenancy->rating,
            'action' => request()->fullUrl(),
        ]);
    }

    public function store(Request $request, string $lease): RedirectResponse
    {
        $tenancy = $this->lease($lease);

        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        Rating::updateOrCreate(
            ['lease_id' => $tenancy->getKey()],
            ['source' => Rating::SOURCE_RENTER, 'score' => (int) $data['score'], 'comment' => $data['comment'] ?? null],
        );

        return redirect()->to($request->fullUrl())->with('rated', true);
    }

    /** Current and past renters can review; not leases that never started. */
    private function lease(string $id): Lease
    {
        $lease = Lease::query()
            ->whereIn('status', [Lease::STATUS_ACTIVE, Lease::STATUS_ENDED, Lease::STATUS_TERMINATED])
            ->with(['renter', 'unit.property', 'rating'])
            ->find($id);

        abort_if($lease === null, 404);

        return $lease;
    }
}
