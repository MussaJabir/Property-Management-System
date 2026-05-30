<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Maintenance;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    public string $priority = MaintenanceRequest::PRIORITY_MEDIUM;

    public ?string $unitId = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    public function mount(): void
    {
        $user = Auth::guard('renter')->user();
        $lease = $user->renter?->leases()->where('status', Lease::STATUS_ACTIVE)->latest('start_date')->first();
        $this->unitId = $lease?->unit_id;
    }

    public function submit()
    {
        $user = Auth::guard('renter')->user();

        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'unitId' => ['required', 'uuid'],
            'photos.*' => ['nullable', 'image', 'max:5120'],
        ]);

        $request = MaintenanceRequest::create([
            'tenant_id' => $user->tenant_id,
            'unit_id' => $this->unitId,
            'reported_by_user_id' => $user->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => MaintenanceRequest::STATUS_PENDING,
            'reported_at' => now(),
        ]);

        foreach ($this->photos as $photo) {
            $request->addMedia($photo->getRealPath())
                ->usingFileName($photo->getClientOriginalName())
                ->toMediaCollection('photos');
        }

        session()->flash('status', __('Maintenance request submitted.'));

        $client = tenant();

        return redirect()->to('/'.$client->slug.'/portal/maintenance/'.$request->id);
    }

    #[Layout('components.layouts.portal', ['authenticated' => true])]
    public function render(): View
    {
        $user = Auth::guard('renter')->user();

        return view('livewire.portal.maintenance.create', [
            'units' => $user->renter?->leases()
                ->with('unit.property')
                ->get()
                ->map(fn (Lease $l): array => [
                    'id' => $l->unit_id,
                    'label' => ($l->unit?->code ?? '—').' · '.($l->unit?->property?->name ?? ''),
                ])
                ->unique('id')
                ->values() ?? collect(),
        ]);
    }
}
