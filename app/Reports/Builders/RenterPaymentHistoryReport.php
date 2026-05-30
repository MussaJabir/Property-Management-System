<?php

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Renter;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Collection;

/**
 * Full payment history for a single renter — every payment recorded across
 * all of their leases, ordered newest first.
 */
class RenterPaymentHistoryReport implements ReportBuilder
{
    public function __construct(public string $renterId) {}

    public function meta(): array
    {
        $renter = Renter::find($this->renterId);

        return [
            'title' => 'Renter Payment History',
            'subtitle' => $renter ? $renter->display_name : 'Unknown renter',
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'invoice_number', 'label' => 'Invoice'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'method', 'label' => 'Method'],
            ['key' => 'reference', 'label' => 'Reference'],
            ['key' => 'amount', 'label' => 'Amount (TZS)', 'align' => 'right'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function rows(): Collection
    {
        return Payment::query()
            ->with(['invoice.lease.unit.property'])
            ->whereHas('invoice.lease', fn ($q) => $q->where('renter_id', $this->renterId))
            ->orderByDesc('payment_date')
            ->get()
            ->map(function (Payment $p): array {
                $unit = $p->invoice?->lease?->unit;
                $unitLabel = $unit ? (($unit->property?->name ?? '—').' / '.$unit->code) : '—';

                return [
                    'date' => optional($p->payment_date)->format('d/m/Y') ?? '—',
                    'invoice_number' => $p->invoice?->invoice_number ?? '—',
                    'unit' => $unitLabel,
                    'method' => str_replace('_', ' ', ucfirst($p->method)),
                    'reference' => $p->reference_number ?? $p->transaction_id ?? '—',
                    'amount' => number_format($p->amount / 100, 0, '.', ','),
                    'status' => ucfirst($p->status),
                ];
            });
    }

    public function summary(): array
    {
        $total = (int) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereHas('invoice.lease', fn ($q) => $q->where('renter_id', $this->renterId))
            ->sum('amount');

        return [
            'Total paid (lifetime)' => 'TZS '.number_format($total / 100, 0, '.', ','),
        ];
    }

    public function filenameSlug(): string
    {
        return 'renter-payments-'.substr($this->renterId, 0, 8).'-'.now()->format('Ymd');
    }
}
