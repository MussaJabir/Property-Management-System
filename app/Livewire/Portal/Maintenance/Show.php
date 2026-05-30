<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Maintenance;

use App\Models\MaintenanceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Show extends Component
{
    public string $requestId = '';

    public function mount(string $request): void
    {
        $user = Auth::guard('renter')->user();

        $req = MaintenanceRequest::query()
            ->where('id', $request)
            ->with(['updates', 'unit.property', 'assignedTo', 'media'])
            ->first();

        if (! $req) {
            throw new NotFoundHttpException;
        }

        $renterUnitIds = $user->renter?->leases()->pluck('unit_id') ?? collect();

        if ($req->reported_by_user_id !== $user->id && ! $renterUnitIds->contains($req->unit_id)) {
            throw new NotFoundHttpException;
        }

        $this->requestId = $req->id;
    }

    #[Layout('components.layouts.portal', ['authenticated' => true])]
    public function render(): View
    {
        $request = MaintenanceRequest::query()
            ->with(['updates.user', 'unit.property', 'assignedTo', 'media'])
            ->findOrFail($this->requestId);

        return view('livewire.portal.maintenance.show', [
            'request' => $request,
        ]);
    }
}
