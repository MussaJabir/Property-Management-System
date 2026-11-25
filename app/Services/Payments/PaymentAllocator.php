<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Shares payments across invoices.
 *
 * A payment pays its own invoice first, then the payer's other unpaid
 * invoices oldest first; whatever is left is credit, used on the payer's next
 * invoice when it is issued. A plan (invoice => amount) overrides the order.
 * A share never exceeds what an invoice still owes, and a payment is never
 * shared beyond its amount. Payments are reversed, never deleted.
 */
final class PaymentAllocator
{
    /**
     * Share a payment out. Runs when a payment is created (PaymentObserver);
     * does nothing for a payment that already has shares.
     *
     * @param  array<string, int>|null  $plan  invoice id => minor units
     */
    public function allocate(Payment $payment, ?array $plan = null): void
    {
        DB::transaction(function () use ($payment, $plan): void {
            Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if ($payment->allocations()->exists()) {
                return;
            }

            $this->fillPayer($payment);
            $left = $payment->amount;

            if ($plan !== null) {
                foreach ($plan as $invoiceId => $amount) {
                    $invoice = $this->payable($payment)->find($invoiceId);
                    if (! $invoice instanceof Invoice || $amount <= 0) {
                        continue;
                    }
                    $share = min((int) $amount, $left, $this->owed($invoice));
                    if ($share > 0) {
                        $this->share($payment, $invoice, $share);
                        $left -= $share;
                    }
                }

                return;
            }

            foreach ($this->targets($payment) as $invoice) {
                if ($left <= 0) {
                    break;
                }
                $share = min($left, $this->owed($invoice));
                if ($share > 0) {
                    $this->share($payment, $invoice, $share);
                    $left -= $share;
                }
            }
        });

        $this->refreshInvoices($payment);
    }

    /**
     * Use the payer's credit (completed payments with money left over) on a
     * newly issued invoice, oldest payment first.
     */
    public function applyCredit(Invoice $invoice): void
    {
        $renterId = $invoice->lease?->renter_id;
        if ($renterId === null || ! $invoice->isOutstanding()) {
            return;
        }

        $applied = DB::transaction(function () use ($invoice, $renterId): int {
            $applied = 0;
            $payments = Payment::query()
                ->where('renter_id', $renterId)
                ->where('status', Payment::STATUS_COMPLETED)
                ->where('currency', $invoice->currency)
                ->orderBy('payment_date')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                $owed = $this->owed($invoice);
                if ($owed <= 0) {
                    break;
                }
                $share = min($payment->unappliedAmount(), $owed);
                if ($share > 0) {
                    $this->share($payment, $invoice, $share);
                    $applied += $share;
                }
            }

            return $applied;
        });

        // Only when credit was used: otherwise the status is left to the usual
        // overdue run, which also tells the renter and adds any late fee.
        if ($applied > 0) {
            $invoice->refresh()->recomputeStatus();
        }
    }

    /**
     * Take a completed payment back with a reason. Its invoices owe again;
     * the payment, its receipt and its shares stay on record.
     */
    public function reverse(Payment $payment, string $reason, ?int $userId = null): void
    {
        if (! $payment->isCompleted()) {
            throw new RuntimeException(__('Only a completed payment can be reversed.'));
        }

        if (trim($reason) === '') {
            throw new RuntimeException(__('Say why the payment is reversed.'));
        }

        $payment->forceFill([
            'status' => Payment::STATUS_REVERSED,
            'reversed_at' => now(),
            'reversed_by_user_id' => $userId,
            'reversal_reason' => trim($reason),
        ])->save();
        // PaymentObserver refreshes the invoices it paid.
    }

    /**
     * Unpaid invoices of the payment's payer it may go to: its own first, then oldest first.
     *
     * @return Collection<int, Invoice>
     */
    public function targets(Payment $payment): Collection
    {
        return $this->payable($payment)
            ->when($payment->invoice_id !== null, fn (Builder $q) => $q->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [(string) $payment->invoice_id]))
            ->orderBy('due_date')
            ->orderBy('issued_at')
            ->get();
    }

    /** The payer's credit: money paid but not yet used on any invoice. */
    public function creditFor(string $renterId, ?string $currency = null): int
    {
        return (int) Payment::query()
            ->where('renter_id', $renterId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->when($currency !== null, fn (Builder $q) => $q->where('currency', $currency))
            ->get()
            ->sum(fn (Payment $payment): int => $payment->unappliedAmount());
    }

    /** Recompute every invoice this payment touches. */
    public function refreshInvoices(Payment $payment): void
    {
        Invoice::query()
            ->whereIn('id', $payment->allocations()->pluck('invoice_id')->push($payment->invoice_id)->filter()->unique())
            ->get()
            ->each(fn (Invoice $invoice) => $invoice->recomputeStatus());
    }

    /** What an invoice still owes, counting every completed share. */
    public function owed(Invoice $invoice): int
    {
        return max(0, $invoice->total_amount - $invoice->paidFromAllocations());
    }

    /** @return Builder<Invoice> */
    private function payable(Payment $payment): Builder
    {
        return Invoice::query()
            ->outstanding()
            ->where('currency', $payment->currency)
            ->where(function (Builder $q) use ($payment): void {
                // A renter's payment only ever pays that renter's invoices; a
                // payment with no renter (a guest's stay) only its own invoice.
                if ($payment->renter_id !== null) {
                    $q->whereHas('lease', fn (Builder $l) => $l->where('renter_id', $payment->renter_id));
                } elseif ($payment->invoice_id !== null) {
                    $q->where('id', $payment->invoice_id);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
    }

    private function share(Payment $payment, Invoice $invoice, int $amount): void
    {
        $allocation = new PaymentAllocation([
            'payment_id' => $payment->getKey(),
            'invoice_id' => $invoice->getKey(),
            'amount' => $amount,
        ]);
        $allocation->forceFill(['tenant_id' => $payment->tenant_id])->save();
    }

    /** A payment follows its payer: take the renter from its invoice when not given. */
    private function fillPayer(Payment $payment): void
    {
        if ($payment->renter_id === null && $payment->invoice_id !== null) {
            $renterId = Invoice::query()->whereKey($payment->invoice_id)->first()?->lease?->renter_id;
            if ($renterId !== null) {
                $payment->forceFill(['renter_id' => $renterId])->saveQuietly();
            }
        }
    }
}
