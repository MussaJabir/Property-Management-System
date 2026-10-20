<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates the rent invoice for a lease's current billing period, so nobody
 * has to type it in every month.
 *
 * Periods are counted from the lease start date, one billing cycle each
 * (1, 3, 6, 12 or custom months), and stop at the lease end date. Only the
 * period that contains today is invoiced, never past ones, so turning this
 * on for an old lease doesn't produce a pile of back invoices.
 *
 * A period is skipped when the lease already has a non-cancelled invoice
 * overlapping it, including hand-made calendar-month ones and drafts. The
 * first period also carries the deposit, when the lease has one.
 *
 * Callers must run inside the lease's tenancy.
 */
class RentInvoiceGenerator
{
    /** Is automatic invoicing on for this workspace? On unless turned off. */
    public static function enabledFor(?Client $client): bool
    {
        return $client !== null && ($client->settings['auto_invoices'] ?? true) !== false;
    }

    /**
     * The billing period containing $today, or null when the lease hasn't
     * started, has ended, or its cycle is unknown.
     *
     * @return array{index: int, start: Carbon, end: Carbon}|null
     */
    public function currentPeriod(Lease $lease, ?Carbon $today = null): ?array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $months = $lease->billingCycleMonths();
        $start = $lease->start_date?->copy()->startOfDay();
        $leaseEnd = $lease->end_date?->copy()->startOfDay();

        if ($months === null || $months < 1 || $start === null || $today->lt($start)) {
            return null;
        }

        if ($leaseEnd !== null && $today->gt($leaseEnd)) {
            return null;
        }

        // Whole cycles elapsed since the start, then nudge either way where
        // month-end dates (a 31st start) make the estimate off by one.
        $index = intdiv((int) floor($start->diffInMonths($today)), $months);
        while ($index > 0 && $start->copy()->addMonthsNoOverflow($index * $months)->gt($today)) {
            $index--;
        }
        while ($start->copy()->addMonthsNoOverflow(($index + 1) * $months)->lte($today)) {
            $index++;
        }

        $periodStart = $start->copy()->addMonthsNoOverflow($index * $months);
        $periodEnd = $start->copy()->addMonthsNoOverflow(($index + 1) * $months)->subDay();

        if ($leaseEnd !== null && $periodEnd->gt($leaseEnd)) {
            $periodEnd = $leaseEnd;
        }

        return ['index' => $index, 'start' => $periodStart, 'end' => $periodEnd];
    }

    /**
     * Issue this period's rent invoice for one lease. Returns the new
     * invoice, or null when there is nothing to invoice.
     */
    public function generateFor(Lease $lease, ?Carbon $today = null): ?Invoice
    {
        if (! $lease->isActive() || $lease->rent_amount <= 0) {
            return null;
        }

        $period = $this->currentPeriod($lease, $today);

        if ($period === null) {
            return null;
        }

        $invoice = DB::transaction(function () use ($lease, $period): ?Invoice {
            // Serialise per lease so the scheduler and the button can't
            // both create the same period's invoice.
            Lease::query()->whereKey($lease->getKey())->lockForUpdate()->first();

            $alreadyBilled = Invoice::query()
                ->where('lease_id', $lease->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->whereDate('billing_period_start', '<=', $period['end']->toDateString())
                ->whereDate('billing_period_end', '>=', $period['start']->toDateString())
                ->exists();

            if ($alreadyBilled) {
                return null;
            }

            $invoice = Invoice::create([
                'lease_id' => $lease->id,
                'status' => Invoice::STATUS_DRAFT,
                'currency' => $lease->currency,
                'billing_period_start' => $period['start']->toDateString(),
                'billing_period_end' => $period['end']->toDateString(),
                'due_date' => $this->dueDate($lease, $period['start'], $period['end'])->toDateString(),
            ]);

            $invoice->items()->create([
                'description' => __('Rent :unit · :from – :to', [
                    'unit' => $lease->unit !== null ? $lease->unit->code : '',
                    'from' => $period['start']->format('d/m/Y'),
                    'to' => $period['end']->format('d/m/Y'),
                ]),
                'type' => InvoiceItem::TYPE_RENT,
                'quantity' => 1,
                'unit_price' => $lease->rent_amount,
            ]);

            if ($period['index'] === 0 && $lease->deposit_amount > 0) {
                $invoice->items()->create([
                    'description' => __('Deposit'),
                    'type' => InvoiceItem::TYPE_DEPOSIT,
                    'quantity' => 1,
                    'unit_price' => $lease->deposit_amount,
                ]);
            }

            return $invoice;
        });

        // Issue outside the lock: it numbers the invoice and tells the renter.
        $invoice?->refresh()->issue();

        return $invoice;
    }

    /**
     * Issue this period's invoices for every active lease in the current
     * workspace. Returns how many were created.
     */
    public function generateForWorkspace(?Carbon $today = null): int
    {
        $created = 0;

        Lease::query()
            ->active()
            ->with('unit')
            ->lazyById(100)
            ->each(function (Lease $lease) use ($today, &$created): void {
                if ($this->generateFor($lease, $today) !== null) {
                    $created++;
                }
            });

        return $created;
    }

    /**
     * The lease's due day in the period's first month, but never before the
     * period starts or after it ends.
     */
    public function dueDate(Lease $lease, Carbon $start, Carbon $end): Carbon
    {
        $day = max(1, min(28, (int) ($lease->payment_due_day ?: 1)));
        $due = $start->copy()->setDay($day);

        if ($due->lt($start)) {
            $due = $start->copy();
        }

        return $due->gt($end) ? $end->copy() : $due;
    }
}
