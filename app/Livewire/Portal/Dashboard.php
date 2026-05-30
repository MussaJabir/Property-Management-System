<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Invoice;
use App\Models\Lease;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Dashboard extends Component
{
    #[Layout('components.layouts.portal', ['authenticated' => true])]
    public function render(): View
    {
        $user = Auth::guard('renter')->user();
        $renter = $user->renter;

        $lease = $renter?->leases()->where('status', Lease::STATUS_ACTIVE)->latest('start_date')->first();

        $nextDue = null;
        $outstandingCents = 0;

        if ($lease) {
            $nextDue = Invoice::query()
                ->where('lease_id', $lease->id)
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                ->orderBy('due_date')
                ->first();

            $outstandingCents = (int) Invoice::query()
                ->where('lease_id', $lease->id)
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
                ->get()
                ->sum(fn (Invoice $i) => $i->balanceDue());
        }

        return view('livewire.portal.dashboard', [
            'renter' => $renter,
            'lease' => $lease,
            'nextDue' => $nextDue,
            'outstanding' => 'TZS '.number_format($outstandingCents / 100, 0, '.', ','),
        ]);
    }
}
