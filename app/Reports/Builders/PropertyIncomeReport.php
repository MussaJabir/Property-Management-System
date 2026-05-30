<?php

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Property;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Property Income — total collected per property over the date range.
 * Money in TZS shillings.
 */
class PropertyIncomeReport implements ReportBuilder
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
    ) {}

    public function meta(): array
    {
        return [
            'title' => 'Property Income',
            'period' => $this->from->format('d/m/Y').' → '.$this->to->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'property', 'label' => 'Property'],
            ['key' => 'payment_count', 'label' => 'Payments', 'align' => 'right'],
            ['key' => 'collected', 'label' => 'Collected (TZS)', 'align' => 'right'],
        ];
    }

    public function rows(): Collection
    {
        // For each property, sum payments where invoice.lease.unit.property_id matches.
        return Property::query()
            ->with(['units.leases.invoices.payments' => fn ($q) => $q
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereBetween('payment_date', [$this->from->toDateString(), $this->to->toDateString()])])
            ->orderBy('name')
            ->get()
            ->map(function (Property $property): array {
                $payments = $property->units
                    ->flatMap(fn ($unit) => $unit->leases->flatMap(fn ($lease) => $lease->invoices->flatMap->payments));

                return [
                    'property' => $property->name,
                    'payment_count' => number_format($payments->count()),
                    'collected' => number_format($payments->sum('amount') / 100, 0, '.', ','),
                ];
            });
    }

    public function summary(): array
    {
        $total = (int) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('payment_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->sum('amount');

        return [
            'Total collected' => 'TZS '.number_format($total / 100, 0, '.', ','),
        ];
    }

    public function filenameSlug(): string
    {
        return 'property-income-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }
}
