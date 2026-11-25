<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Renter;
use App\Services\Payments\TenantStatement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/** The renter's own statement: what was charged, what they paid, and the balance. */
class Statement extends Component
{
    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = today()->subMonthsNoOverflow(3)->startOfMonth()->toDateString();
        $this->to = today()->toDateString();
    }

    #[Layout('components.layouts.portal', ['authenticated' => true])]
    public function render(): View
    {
        $renter = Auth::guard('renter')->user()?->renter;

        return view('livewire.portal.statement', [
            'statement' => $renter instanceof Renter
                ? app(TenantStatement::class)->build($renter, self::date($this->from, today()->subMonthsNoOverflow(3)->startOfMonth()), self::date($this->to, today()))
                : null,
        ]);
    }

    public static function date(string $value, Carbon $fallback): Carbon
    {
        try {
            return $value !== '' ? Carbon::parse($value) : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
