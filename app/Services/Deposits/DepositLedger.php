<?php

declare(strict_types=1);

namespace App\Services\Deposits;

use App\Models\DepositTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tenant deposits, kept apart from rent. A deposit counts as received once
 * the invoice carrying it is fully paid; staff deduct (with a reason) or
 * refund it; a renewal can carry it over. Nothing here is rental income.
 */
final class DepositLedger
{
    /** Minor units of deposit held on a lease now. */
    public function held(Lease $lease): int
    {
        return (int) $lease->depositTransactions()->get()->sum(fn (DepositTransaction $t): int => $t->signedAmount());
    }

    /** Deposit held across all of a renter's leases. */
    public function heldForRenter(string $renterId): int
    {
        return (int) DepositTransaction::query()
            ->whereHas('lease', fn ($q) => $q->where('renter_id', $renterId))
            ->get()
            ->sum(fn (DepositTransaction $t): int => $t->signedAmount());
    }

    /**
     * Keep the "received" entry in step with an invoice's deposit lines:
     * there while the invoice is fully paid, gone if it no longer is (a
     * reversed payment). Called whenever the invoice's status is worked out.
     */
    public function syncInvoice(Invoice $invoice): void
    {
        if ($invoice->lease_id === null) {
            return;
        }

        $deposit = (int) $invoice->items()->where('type', InvoiceItem::TYPE_DEPOSIT)->sum('line_total');
        $entry = DepositTransaction::query()->where('invoice_id', $invoice->id)->where('type', DepositTransaction::TYPE_RECEIVED)->first();

        if ($invoice->isPaid() && $deposit > 0) {
            if ($entry === null) {
                $this->add($invoice->lease_id, DepositTransaction::TYPE_RECEIVED, $deposit, invoiceId: $invoice->id, on: $invoice->paid_at?->toDateString());
            } elseif ($entry->amount !== $deposit) {
                $entry->update(['amount' => $deposit]);
            }

            return;
        }

        $entry?->delete();
    }

    /** A deposit paid outside an invoice, e.g. before the workspace used PMS. */
    public function receive(Lease $lease, int $amount, string $on, ?string $reason = null, ?int $userId = null): DepositTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException(__('Enter an amount above zero.'));
        }

        return $this->add($lease->id, DepositTransaction::TYPE_RECEIVED, $amount, $reason, $userId, on: $on);
    }

    /** Keep part of the deposit, for damage or unpaid bills. */
    public function deduct(Lease $lease, int $amount, string $reason, ?int $userId = null): DepositTransaction
    {
        return $this->takeOut($lease, DepositTransaction::TYPE_DEDUCTION, $amount, $reason, $userId);
    }

    /** Pay the deposit (or part of it) back to the tenant. */
    public function refund(Lease $lease, int $amount, ?string $reason = null, ?int $userId = null): DepositTransaction
    {
        return $this->takeOut($lease, DepositTransaction::TYPE_REFUND, $amount, $reason, $userId);
    }

    /**
     * Move what a renewal carries over from the old lease to the new one.
     * Only what is actually held moves.
     */
    public function carryOver(Lease $from, Lease $to, int $amount, ?int $userId = null): void
    {
        DB::transaction(function () use ($from, $to, $amount, $userId): void {
            $move = min($amount, $this->held($from));
            if ($move <= 0) {
                return;
            }
            $reason = __('Lease renewal');
            $this->add($from->id, DepositTransaction::TYPE_CARRIED_OUT, $move, $reason, $userId);
            $this->add($to->id, DepositTransaction::TYPE_CARRIED_IN, $move, $reason, $userId);
        });
    }

    private function takeOut(Lease $lease, string $type, int $amount, ?string $reason, ?int $userId): DepositTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException(__('Enter an amount above zero.'));
        }

        if ($type === DepositTransaction::TYPE_DEDUCTION && trim((string) $reason) === '') {
            throw new RuntimeException(__('Say why part of the deposit is kept.'));
        }

        return DB::transaction(function () use ($lease, $type, $amount, $reason, $userId): DepositTransaction {
            Lease::query()->whereKey($lease->getKey())->lockForUpdate()->first();

            $held = $this->held($lease);
            if ($amount > $held) {
                throw new RuntimeException(__('Only :amount is held.', ['amount' => $lease->currency.' '.number_format($held / 100, 0, '.', ',')]));
            }

            return $this->add($lease->id, $type, $amount, $reason, $userId);
        });
    }

    private function add(string $leaseId, string $type, int $amount, ?string $reason = null, ?int $userId = null, ?string $invoiceId = null, ?string $on = null): DepositTransaction
    {
        $lease = Lease::query()->findOrFail($leaseId);
        $entry = new DepositTransaction([
            'lease_id' => $leaseId,
            'type' => $type,
            'amount' => $amount,
            'occurred_on' => $on ?? today()->toDateString(),
            'reason' => $reason !== null ? trim($reason) : null,
            'invoice_id' => $invoiceId,
            'recorded_by_user_id' => $userId,
        ]);
        $entry->forceFill(['tenant_id' => $lease->tenant_id])->save();

        return $entry;
    }
}
