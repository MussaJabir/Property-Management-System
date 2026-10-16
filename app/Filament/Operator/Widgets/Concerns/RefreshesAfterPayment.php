<?php

declare(strict_types=1);

namespace App\Filament\Operator\Widgets\Concerns;

use Livewire\Attributes\On;

/**
 * Re-render when a payment is recorded anywhere on the page (the Record
 * payment action dispatches the event), so figures and lists stay current
 * without a reload.
 */
trait RefreshesAfterPayment
{
    #[On('pms-payment-recorded')]
    public function refreshAfterPayment(): void
    {
        // Calling a Livewire method is enough to re-render the component.
    }
}
