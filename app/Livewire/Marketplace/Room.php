<?php

declare(strict_types=1);

namespace App\Livewire\Marketplace;

use App\Models\Client;
use App\Models\Rating;
use App\Models\Unit;
use App\Services\Marketplace\ListingSearch;
use App\Support\RoomHighlights;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One listed room inside the PMS marketplace (/browse/{client}/{room}):
 * the landlord's room page content in the marketplace frame, with a Back
 * link to the same search, the landlord's card and places nearby from
 * every landlord. It runs inside the landlord's workspace, so the booking
 * form and WhatsApp work exactly as on their own site.
 *
 * A room that is not on the marketplace (or a demo one) goes to the
 * landlord's own room page instead, so shared links never break.
 */
class Room extends Component
{
    public Unit $unit;

    /** The /browse search this visitor came from. */
    #[Locked]
    public string $backUrl = '';

    public function mount(Unit $unit, ListingSearch $listings): void
    {
        $client = $this->client();
        $this->unit = $unit;

        if ($client->is_demo || ! $listings->isListed($unit)) {
            $this->redirect(route('public.units.show', ['tenant' => $client->slug, 'unit' => $unit->getKey()]));

            return;
        }

        $this->unit = $unit->load(['property.location', 'media', 'property.media']);
        $this->backUrl = route('marketplace.browse', ListingSearch::browseQuery(request()->query()));
    }

    #[Layout('components.layouts.marketplace')]
    public function render(ListingSearch $listings): View
    {
        $client = $this->client();

        $gallery = $this->unit->hasOwnPhotos()
            ? $this->unit->getMedia('photos')
            : ($this->unit->property?->getMedia('photos') ?? collect());

        $reviews = Rating::query()
            ->published()
            ->forUnit($this->unit->getKey())
            ->whereNotNull('comment')
            ->with(['booking.guest', 'lease.renter'])
            ->latest('published_at')
            ->limit(12)
            ->get();

        $nearby = $listings->nearby($this->unit);

        // Nearby rooms belong to other landlords: read their scores outside
        // this workspace.
        $ratings = tenancy()->central(fn () => Rating::summaryForUnits(
            [(string) $this->unit->getKey(), ...$nearby->map(fn (Unit $u): string => (string) $u->getKey())->all()]
        ));

        $price = $this->unit->publicPrices()[0] ?? null;
        $location = $this->unit->property?->location;

        return view('livewire.marketplace.room', [
            'client' => $client,
            'gallery' => $gallery,
            'reviews' => $reviews,
            'summary' => $ratings[(string) $this->unit->getKey()] ?? null,
            'nearby' => $nearby,
            'ratings' => $ratings,
            'landlordCount' => tenancy()->central(fn () => $listings->countFor($client)),
            'landlordUrl' => route('marketplace.browse', ['landlord' => $client->getKey()]),
            'regionUrl' => $location?->region ? route('marketplace.browse', ['q' => $location->region]) : null,
            'highlights' => RoomHighlights::for($this->unit),
        ])->layoutData([
            'phoneHeader' => false,
            'title' => $this->unit->publicTitle(),
            'description' => collect([
                $price ? $price['currency'].' '.$price['amount'].' / '.$price['per'] : null,
                $location ? collect([$location->name, $location->district])->filter()->unique()->implode(', ') : null,
                implode(' · ', $this->unit->publicFacts()) ?: null,
            ])->filter()->implode(' · '),
            'image' => $this->unit->coverImageUrl('card'),
            'url' => route('marketplace.room', ['tenant' => $client->slug, 'unit' => $this->unit->getKey()]),
        ]);
    }

    private function client(): Client
    {
        $client = tenant();

        abort_unless($client instanceof Client, 404);

        return $client;
    }
}
