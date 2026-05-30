<?php

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Invoice;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Collection;

/**
 * Outstanding Rent — all open invoices (unpaid / partial / overdue) grouped
 * by renter. Snapshot of who owes what right now; no date range.
 */
class OutstandingRentReport implements ReportBuilder
{
    public function __construct(public ?string $propertyId = null) {}

    public function meta(): array
    {
        return [
            'title' => 'Outstanding Rent',
            'subtitle' => 'Open invoices as of '.now()->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'invoice_number', 'label' => 'Invoice'],
            ['key' => 'renter', 'label' => 'Renter'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'due_date', 'label' => 'Due'],
            ['key' => 'total', 'label' => 'Total (TZS)', 'align' => 'right'],
            ['key' => 'paid', 'label' => 'Paid (TZS)', 'align' => 'right'],
            ['key' => 'balance', 'label' => 'Balance (TZS)', 'align' => 'right'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function rows(): Collection
    {
        $query = Invoice::query()
            ->outstanding()
            ->with(['lease.renter', 'lease.unit.property'])
            ->orderBy('due_date');

        if ($this->propertyId) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('property_id', $this->propertyId));
        }

        return $query->get()->map(function (Invoice $invoice): array {
            $unit = $invoice->lease?->unit;
            $unitLabel = $unit ? (($unit->property?->name ?? '—').' / '.$unit->code) : '—';

            return [
                'invoice_number' => $invoice->invoice_number ?? '—',
                'renter' => $invoice->lease?->renter?->display_name ?? '—',
                'unit' => $unitLabel,
                'due_date' => optional($invoice->due_date)->format('d/m/Y') ?? '—',
                'total' => number_format($invoice->total_amount / 100, 0, '.', ','),
                'paid' => number_format($invoice->amount_paid / 100, 0, '.', ','),
                'balance' => number_format($invoice->balanceDue() / 100, 0, '.', ','),
                'status' => ucfirst($invoice->status),
            ];
        });
    }

    public function summary(): array
    {
        $query = Invoice::query()->outstanding();

        if ($this->propertyId) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('property_id', $this->propertyId));
        }

        $invoices = $query->get();
        $totalBalance = $invoices->sum(fn (Invoice $i): int => $i->balanceDue());
        $overdueBalance = $invoices
            ->where('status', Invoice::STATUS_OVERDUE)
            ->sum(fn (Invoice $i): int => $i->balanceDue());

        return [
            'Open invoices' => number_format($invoices->count()),
            'Overdue balance' => 'TZS '.number_format($overdueBalance / 100, 0, '.', ','),
            'Total outstanding' => 'TZS '.number_format($totalBalance / 100, 0, '.', ','),
        ];
    }

    public function filenameSlug(): string
    {
        return 'outstanding-rent-'.now()->format('Ymd');
    }
}
