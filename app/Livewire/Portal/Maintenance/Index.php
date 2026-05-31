<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Maintenance;

use App\Models\MaintenanceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Layout('components.layouts.portal', ['authenticated' => true])]
    public function render(): View
    {
        $user = Auth::guard('renter')->user();
        $unitIds = $user->renter?->leases()->pluck('unit_id') ?? collect();

        $requests = MaintenanceRequest::query()
            ->where('reported_by_user_id', $user->id)
            ->orWhere(fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->with('unit.property')
            ->orderByDesc('reported_at')
            ->paginate(15);

        return view('livewire.portal.maintenance.index', [
            'requests' => $requests,
        ]);
    }
}
