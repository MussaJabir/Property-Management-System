<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Invoices;

use App\Models\Invoice;
use App\Models\Renter;
use App\Services\Payments\PaymentRequester;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $status = 'all';

    /**
     * Whether the workspace takes mobile money through Selcom. Worked out
     * on the first (tenant-routed) render and kept for later updates.
     */
    public bool $canPayByPhone = false;

    public function mount(): void
    {
        $this->canPayByPhone = PaymentRequester::available();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Layout('components.layouts.portal', ['authenticated' => true])]
    public function render(): View
    {
        $user = Auth::guard('renter')->user();
        $renter = $user?->renter;
        $leaseIds = $user->renter?->leases()->pluck('id') ?? collect();

        $query = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->with(['payments.receipt', 'lease.unit.property'])
            ->orderByDesc('billing_period_start');

        if ($this->status === 'open') {
            $query->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE]);
        } elseif ($this->status === 'paid') {
            $query->where('status', Invoice::STATUS_PAID);
        }

        return view('livewire.portal.invoices.index', [
            'invoices' => $query->paginate(15),
            'payerPhone' => $renter instanceof Renter ? (string) $renter->phone : null,
        ]);
    }
}
