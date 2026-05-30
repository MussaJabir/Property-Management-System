<?php

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Payment;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Monthly Rent Collection — sum of completed payments per month over the
 * selected date range. Optional property filter narrows to payments linked
 * to invoices on leases on units in that property.
 */
class MonthlyRentCollectionReport implements ReportBuilder
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public ?string $propertyId = null,
    ) {}

    public function meta(): array
    {
        return [
            'title' => 'Monthly Rent Collection',
            'period' => $this->from->format('d/m/Y').' → '.$this->to->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'month', 'label' => 'Month'],
            ['key' => 'payment_count', 'label' => 'Payments', 'align' => 'right'],
            ['key' => 'total', 'label' => 'Collected (TZS)', 'align' => 'right'],
        ];
    }

    public function rows(): Collection
    {
        $query = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('payment_date', [$this->from->toDateString(), $this->to->toDateString()]);

        if ($this->propertyId) {
            $query->whereHas('invoice.lease.unit', fn ($q) => $q->where('property_id', $this->propertyId));
        }

        $payments = $query->get(['payment_date', 'amount']);

        return $payments
            ->groupBy(fn (Payment $p): string => $p->payment_date->format('Y-m'))
            ->map(function (Collection $group, string $ym): array {
                $monthStart = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();

                return [
                    'month' => $monthStart->translatedFormat('F Y'),
                    'payment_count' => number_format($group->count()),
                    'total' => number_format($group->sum('amount') / 100, 0, '.', ','),
                ];
            })
            ->sortKeys()
            ->values();
    }

    public function summary(): array
    {
        $query = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('payment_date', [$this->from->toDateString(), $this->to->toDateString()]);

        if ($this->propertyId) {
            $query->whereHas('invoice.lease.unit', fn ($q) => $q->where('property_id', $this->propertyId));
        }

        $total = (int) $query->sum('amount');

        return [
            'Total collected' => 'TZS '.number_format($total / 100, 0, '.', ','),
        ];
    }

    public function filenameSlug(): string
    {
        return 'monthly-rent-collection-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }
}
