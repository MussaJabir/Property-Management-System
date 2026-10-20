<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Units extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'property')]
    public string $propertyId = '';

    #[Url(as: 'location')]
    public string $locationId = '';

    #[Url(as: 'min')]
    public ?int $minRent = null;

    #[Url(as: 'max')]
    public ?int $maxRent = null;

    /** '' = every room, 'night' = let by the night, 'month' = on a lease. */
    #[Url(as: 'stay')]
    public string $mode = '';

    /** @var array<int, string> selected amenity keys (AND-filtered) */
    #[Url(as: 'amenities')]
    public array $amenities = [];

    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'type', 'mode', 'propertyId', 'locationId', 'minRent', 'maxRent', 'amenities');
        $this->resetPage();
    }

    #[Layout('components.layouts.public')]
    public function render(): View
    {
        $query = Unit::query()
            ->listedPublicly()
            ->with(['property.location', 'media', 'property.media'])
            ->orderByDesc('updated_at');

        if ($this->search !== '') {
            $query->where(fn ($q) => $q
                ->where('code', 'ilike', '%'.$this->search.'%')
                ->orWhere('description', 'ilike', '%'.$this->search.'%')
                ->orWhereHas('property', fn ($p) => $p->where('name', 'ilike', '%'.$this->search.'%'))
            );
        }

        match ($this->mode) {
            'night' => $query->shortStay(),
            'month' => $query->longTerm(),
            default => null,
        };

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->propertyId !== '') {
            $query->where('property_id', $this->propertyId);
        }

        if ($this->locationId !== '') {
            $query->whereHas('property', fn ($q) => $q->where('location_id', $this->locationId));
        }

        // Rent filters compare rent, so rooms let only by the night (no
        // rent) drop out rather than matching any budget at 0.
        if ($this->minRent !== null && $this->minRent > 0) {
            $query->longTerm()->where('rent_amount', '>=', $this->minRent * 100);
        }

        if ($this->maxRent !== null && $this->maxRent > 0) {
            $query->longTerm()->where('rent_amount', '<=', $this->maxRent * 100);
        }

        // Amenity filter: unit must have ALL selected amenities (AND).
        $selectedAmenities = array_values(array_intersect($this->amenities, Unit::AMENITIES));
        foreach ($selectedAmenities as $amenity) {
            $query->whereJsonContains('amenities', $amenity);
        }

        $listed = Unit::query()->listedPublicly();

        return view('livewire.public.units', [
            'units' => $query->paginate(12),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('region')->get(['id', 'region', 'district']),
            // Only types and ways of letting that are actually on offer.
            'types' => (clone $listed)->distinct()->orderBy('type')->pluck('type')->filter()->values()->all(),
            'hasShortStays' => (clone $listed)->shortStay()->exists(),
            'hasLongTerm' => (clone $listed)->longTerm()->exists(),
            'amenityOptions' => Unit::amenityOptions(),
        ]);
    }
}
