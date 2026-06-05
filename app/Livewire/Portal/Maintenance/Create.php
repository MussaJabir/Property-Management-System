<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Maintenance;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        // Authorisation: a renter may only file against a unit they hold (or
        // held) a lease on — not any UUID they post. Same source as the unit
        // dropdown in render(), so the form options and the rule stay in sync.
        $allowedUnitIds = $user->renter
            ? $user->renter->leases()->pluck('unit_id')->filter()->unique()->values()->all()
            : [];

        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'unitId' => ['required', 'uuid', Rule::in($allowedUnitIds)],
            // Restrict to safe raster types — no SVG (can carry scripts).
            'photos.*' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        // tenant_id is set directly (not mass-assigned): this is a renter-portal
        // Livewire submit where path-based tenancy isn't active, so auto-fill
        // can't run and tenant_id is excluded from $fillable. $user->tenant_id
        // is the client slug, also used for the redirect (tenant() is null here).
        $request = new MaintenanceRequest([
            'unit_id' => $this->unitId,
            'reported_by_user_id' => $user->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => MaintenanceRequest::STATUS_PENDING,
            'reported_at' => now(),
        ]);
        $request->tenant_id = $user->tenant_id;
        $request->save();

        foreach ($this->photos as $photo) {
            // Generated filename — never trust the client-supplied original name.
            $request->addMedia($photo->getRealPath())
                ->usingFileName(Str::random(40).'.'.$photo->getClientOriginalExtension())
                ->toMediaCollection('photos');
        }

        session()->flash('status', __('Maintenance request submitted.'));

        return redirect()->to('/'.$user->tenant_id.'/portal/maintenance/'.$request->id);
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
