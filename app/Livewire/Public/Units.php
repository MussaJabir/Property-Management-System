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

    /** @var array<int, string> selected amenity keys (AND-filtered) */
    #[Url(as: 'amenities')]
    public array $amenities = [];

    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    #[Layout('components.layouts.public')]
    public function render(): View
    {
        $query = Unit::query()
            ->where('status', Unit::STATUS_VACANT)
            ->with(['property.location', 'media', 'property.media'])
            ->orderByDesc('updated_at');

        if ($this->search !== '') {
            $query->where(fn ($q) => $q
                ->where('code', 'ilike', '%'.$this->search.'%')
                ->orWhere('description', 'ilike', '%'.$this->search.'%')
            );
        }

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->propertyId !== '') {
            $query->where('property_id', $this->propertyId);
        }

        if ($this->locationId !== '') {
            $query->whereHas('property', fn ($q) => $q->where('location_id', $this->locationId));
        }

        if ($this->minRent !== null && $this->minRent > 0) {
            $query->where('rent_amount', '>=', $this->minRent * 100);
        }

        if ($this->maxRent !== null && $this->maxRent > 0) {
            $query->where('rent_amount', '<=', $this->maxRent * 100);
        }

        // Amenity filter: unit must have ALL selected amenities (AND).
        $selectedAmenities = array_values(array_intersect($this->amenities, Unit::AMENITIES));
        foreach ($selectedAmenities as $amenity) {
            $query->whereJsonContains('amenities', $amenity);
        }

        return view('livewire.public.units', [
            'units' => $query->paginate(12),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('region')->get(['id', 'region', 'district']),
            'types' => ['room', 'apartment', 'business_frame', 'office', 'shop', 'warehouse'],
            'amenityOptions' => Unit::amenityOptions(),
        ]);
    }
}
