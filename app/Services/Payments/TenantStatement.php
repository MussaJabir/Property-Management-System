<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Renter;
use App\Services\Deposits\DepositLedger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * A renter's statement for a period: every invoice charged and every payment
 * received (and any reversal), with the running balance after each. A
 * negative balance is credit. Shared by the dashboard, the PDF and the portal.
 *
 * Callers must run inside the renter's tenancy.
 */
final class TenantStatement
{
    /**
     * @return array{
     *     renter: Renter, from: Carbon, to: Carbon, currency: string,
     *     opening: int, charged: int, paid: int, closing: int, deposit_held: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function build(Renter $renter, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $entries = collect($this->entries($renter));

        $opening = (int) $entries->filter(fn (array $e): bool => $e['date']->lt($from))
            ->sum(fn (array $e): int => ($e['charged'] ?? 0) - ($e['paid'] ?? 0));

        $balance = $opening;
        $rows = $entries->filter(fn (array $e): bool => $e['date']->betweenIncluded($from, $to))
            ->map(function (array $e) use (&$balance): array {
                $balance += ($e['charged'] ?? 0) - ($e['paid'] ?? 0);

                return [...$e, 'balance' => $balance];
            })
            ->values()
            ->all();

        return [
            'renter' => $renter,
            'from' => $from,
            'to' => $to,
            'currency' => $entries->first()['currency'] ?? 'TZS',
            'opening' => $opening,
            'charged' => (int) collect($rows)->sum(fn (array $r): int => $r['charged'] ?? 0),
            'paid' => (int) collect($rows)->sum(fn (array $r): int => $r['paid'] ?? 0),
            'closing' => $balance,
            // Kept apart: the deposit is not part of what is owed.
            'deposit_held' => app(DepositLedger::class)->heldForRenter((string) $renter->getKey()),
            'rows' => $rows,
        ];
    }

    /** The statement as a PDF (headless Chrome). */
    public function pdf(Renter $renter, Carbon $from, Carbon $to): string
    {
        $html = View::make('billing.statement', [
            'statement' => $this->build($renter, $from, $to),
            'client' => Client::find($renter->tenant_id),
        ])->render();

        return Browsershot::html($html)->format('A4')->noSandbox()->showBackground()->pdf();
    }

    /**
     * Every charge, payment and reversal, oldest first; charges before
     * payments on the same day.
     *
     * @return list<array{date: CarbonInterface, what: string, note: string|null, ref: string|null, charged: int|null, paid: int|null, currency: string, reversal: bool, order: int}>
     */
    private function entries(Renter $renter): array
    {
        $entries = [];

        $invoices = Invoice::query()
            ->whereHas('lease', fn ($q) => $q->where('renter_id', $renter->getKey()))
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->with('items')
            ->get();

        foreach ($invoices as $invoice) {
            $entries[] = [
                'date' => $invoice->issued_at?->copy() ?? now(),
                'what' => $this->describe($invoice),
                'note' => null,
                'ref' => $invoice->invoice_number,
                'charged' => $invoice->total_amount,
                'paid' => null,
                'currency' => $invoice->currency,
                'reversal' => false,
                'order' => 0,
            ];
        }

        $payments = Payment::query()
            ->where('renter_id', $renter->getKey())
            ->whereIn('status', [Payment::STATUS_COMPLETED, Payment::STATUS_REVERSED])
            ->with(['receipt', 'reversedBy'])
            ->get();

        foreach ($payments as $payment) {
            $entries[] = [
                'date' => $payment->payment_date?->copy() ?? now(),
                'what' => (string) __('Payment · :method', ['method' => $payment->method_label]),
                'note' => null,
                'ref' => $payment->receipt?->receipt_number,
                'charged' => null,
                'paid' => $payment->amount,
                'currency' => $payment->currency,
                'reversal' => false,
                'order' => 1,
            ];

            if ($payment->isReversed()) {
                $entries[] = [
                    'date' => $payment->reversed_at?->copy() ?? now(),
                    'what' => (string) __('Payment reversed: :reason', ['reason' => $payment->reversal_reason ?? '']),
                    'note' => $payment->reversedBy !== null ? (string) __('Reversed by :name', ['name' => $payment->reversedBy->name]) : null,
                    'ref' => $payment->receipt?->receipt_number,
                    'charged' => null,
                    'paid' => -$payment->amount,
                    'currency' => $payment->currency,
                    'reversal' => true,
                    'order' => 2,
                ];
            }
        }

        usort($entries, fn (array $a, array $b): int => [$a['date']->toDateString(), $a['order']] <=> [$b['date']->toDateString(), $b['order']]);

        return $entries;
    }

    /** "Rent A3 · 01/10/2026 – 31/10/2026" or "Rent … and 1 more" */
    private function describe(Invoice $invoice): string
    {
        $lines = $invoice->items->pluck('description')->filter()->values();

        return (string) match ($lines->count()) {
            0 => __('Invoice'),
            1 => $lines->first(),
            default => __(':first and :count more', ['first' => $lines->first(), 'count' => $lines->count() - 1]),
        };
    }
}
