<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class UnitShow extends Component
{
    public Unit $unit;

    /**
     * Laravel implicit route-model binding resolves {unit} by primary key.
     * The TenantScopedModel global scope constrains the lookup to the active
     * client, so a unit from another client (or a bad id) yields a 404
     * automatically — no manual guard needed.
     */
    public function mount(Unit $unit): void
    {
        $this->unit = $unit->load(['property.location', 'media', 'property.media']);
    }

    #[Layout('components.layouts.public')]
    public function render(): View
    {
        // Gallery: prefer the unit's own photos; fall back to the property's
        // so the page is never imageless.
        $gallery = $this->unit->hasOwnPhotos()
            ? $this->unit->getMedia('photos')
            : ($this->unit->property?->getMedia('photos') ?? collect());

        // A few other vacant units from the same client to keep browsing.
        $more = Unit::query()
            ->where('status', Unit::STATUS_VACANT)
            ->whereKeyNot($this->unit->getKey())
            ->with(['property.location', 'media', 'property.media'])
            ->latest('updated_at')
            ->limit(3)
            ->get();

        return view('livewire.public.unit-show', [
            'gallery' => $gallery,
            'more' => $more,
        ]);
    }
}
