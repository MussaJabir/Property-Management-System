<?php

declare(strict_types=1);

namespace App\Livewire\Marketplace;

use App\Models\Client;
use App\Models\Rating;
use App\Models\Unit;
use App\Services\Marketplace\ListingSearch;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * PMS marketplace (/browse): vacant units from every client that listed
 * them. Cards open the marketplace room page (Room), which keeps this
 * search for its Back link.
 */
class Browse extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** '' = any, 'night' = by the night, 'month' = on a lease. */
    #[Url(as: 'stay')]
    public string $stay = '';

    #[Url(as: 'max')]
    public ?int $max = null;

    #[Url(as: 'bedrooms')]
    public ?int $bedrooms = null;

    /** @var list<string> */
    #[Url(as: 'amenities')]
    public array $amenities = [];

    #[Url(as: 'sort')]
    public string $sort = ListingSearch::SORT_FEATURED;

    /** Kind of place (phone chips): rooms, apartments or business. */
    #[Url(as: 'type')]
    public string $type = '';

    /** One landlord's places only ("See all their places" on a room page). */
    #[Url(as: 'landlord')]
    public string $landlord = '';

    public function updating(string $name): void
    {
        $this->resetPage();
    }

    public function toggleAmenity(string $amenity): void
    {
        $this->amenities = in_array($amenity, $this->amenities, true)
            ? array_values(array_diff($this->amenities, [$amenity]))
            : [...$this->amenities, $amenity];
        $this->resetPage();
    }

    /** A type chip on phones; "Short stays" is the by-the-night filter. */
    public function pickType(string $type): void
    {
        if ($type === 'night') {
            $this->stay = $this->stay === 'night' ? '' : 'night';
            $this->type = '';
        } else {
            $this->type = array_key_exists($type, ListingSearch::TYPE_GROUPS) ? $type : '';
            $this->stay = '';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'stay', 'max', 'bedrooms', 'amenities', 'sort', 'landlord', 'type');
        $this->resetPage();
    }

    #[Layout('components.layouts.marketplace')]
    public function render(ListingSearch $listings): View
    {
        $filters = [
            'q' => $this->search,
            'stay' => $this->stay,
            'max' => $this->max,
            'bedrooms' => $this->bedrooms,
            'amenities' => $this->amenities,
            'sort' => $this->sort,
            'landlord' => $this->landlord,
            'type' => $this->type,
        ];
        $units = $listings->search($filters);

        // Phones: with no search yet, show rows by area (only a type chip
        // applies); any search shows the two-per-row grid instead.
        $searching = trim($this->search) !== '' || $this->max || $this->bedrooms || $this->amenities !== [] || $this->landlord !== '';
        $rows = $searching ? [] : $listings->rowsByRegion(['type' => $this->type, 'stay' => $this->stay]);

        return view('livewire.marketplace.browse', [
            'units' => $units,
            'ratings' => Rating::summaryForUnits(array_values(array_unique([
                ...array_map(fn (Unit $unit): string => (string) $unit->getKey(), $units->items()),
                ...collect($rows)->flatMap(fn (array $row) => $row['units'])->map(fn (Unit $unit): string => (string) $unit->getKey())->all(),
            ]))),
            'regions' => $listings->regions(),
            'amenityOptions' => array_intersect_key(Unit::amenityOptions(), array_flip(['wifi', 'water_247', 'parking', 'security', 'furnished', 'air_conditioning', 'backup_power'])),
            'rows' => $rows,
            'areas' => $listings->regionSummaries(),
            'searching' => $searching,
            'typeChips' => [
                '' => __('marketplace.type_all'),
                'rooms' => __('marketplace.type_rooms'),
                'apartments' => __('marketplace.type_apartments'),
                'night' => __('marketplace.stay_night_short'),
                'business' => __('marketplace.type_business'),
            ],
            'filtered' => $this->type !== '' || $this->search !== '' || $this->stay !== '' || $this->max || $this->bedrooms || $this->amenities !== [] || $this->landlord !== '',
            // Room pages link back to this same search.
            'searchQuery' => ListingSearch::browseQuery($filters),
            'landlordName' => $this->landlord !== '' ? Client::query()->whereKey($this->landlord)->value('name') : null,
        ]);
    }
}
