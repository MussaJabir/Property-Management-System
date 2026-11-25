<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Services\Deposits\DepositLedger;
use App\Services\InvoiceNumberGenerator;
use App\Services\Payments\PaymentAllocator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Invoice = a billable charge against a Lease for one billing period, or
 * against a Booking for a short stay (exactly one of lease_id / booking_id).
 *
 * Money is in TZS cents. amount_paid is denormalized — kept in sync via
 * Invoice::recomputeStatus(), called by the Payment observer.
 *
 * Status machine:
 *   draft   → issue()   → unpaid          (locks the invoice_number)
 *   draft   → cancel()  → cancelled
 *   any-non-final → cancel() refused if any completed payment exists
 *   unpaid|partial → recomputeStatus()    → partial / paid (overdue if past due)
 *   unpaid|partial → markOverdueIfDue()   → overdue (daily scheduler)
 *
 * @property string $invoice_number
 * @property int $subtotal
 * @property int $tax_amount
 * @property int $total_amount
 * @property int $amount_paid
 * @property string $currency
 * @property string $status
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $due_date
 * @property-read Lease|null $lease
 * @property-read Booking|null $booking
 * @property-read string $bill_to_name
 * @property-read string|null $unit_code
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 */
class Invoice extends Model
{
    use Audited, HasFactory, HasUuids, SoftDeletes, TenantScopedModel;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'lease_id',
        'booking_id',
        'invoice_number',
        'billing_period_start',
        'billing_period_end',
        'issued_at',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'currency',
        'status',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'amount_paid' => 'integer',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** Short-stay invoices belong to a booking instead of a lease. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Mobile money prompts sent through Selcom for this invoice. */
    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** Minor units paid on this invoice by completed payments. */
    public function paidFromAllocations(): int
    {
        return (int) $this->allocations()
            ->whereHas('payment', fn (Builder $q) => $q->where('status', Payment::STATUS_COMPLETED))
            ->sum('amount');
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    /* ----- State machine ----- */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Issued and not yet fully paid: unpaid, partial or overdue. */
    public function isOutstanding(): bool
    {
        return in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIAL, self::STATUS_OVERDUE], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Promote a draft invoice to unpaid: assigns the invoice_number from the
     * per-tenant yearly sequence and stamps issued_at. Idempotent on already-
     * issued invoices (no-op).
     */
    public function issue(): void
    {
        if (! $this->isDraft()) {
            return;
        }

        if ($this->total_amount <= 0) {
            throw new RuntimeException('Cannot issue an invoice with no items.');
        }

        DB::transaction(function (): void {
            $this->invoice_number = app(InvoiceNumberGenerator::class)->next($this->tenant_id);
            $this->status = self::STATUS_UNPAID;
            $this->issued_at = now();
            $this->save();
        });

        // Money the renter paid ahead (credit) pays this invoice first.
        try {
            app(PaymentAllocator::class)->applyCredit($this);
        } catch (\Throwable $e) {
            Log::warning('Applying credit to a new invoice failed', ['invoice_id' => $this->id, 'error' => $e->getMessage()]);
        }

        try {
            $renterUser = $this->lease?->renter?->user;
            if ($renterUser) {
                $renterUser->notify(new InvoiceIssuedNotification($this->fresh()));
            }
        } catch (\Throwable $e) {
            Log::warning('InvoiceIssuedNotification failed', [
                'invoice_id' => $this->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel a non-final invoice. Refuses if any completed payment has been
     * recorded — those need refunding first, which is out of v1 scope.
     */
    public function cancel(): void
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED], true)) {
            return;
        }

        $hasCompletedPayment = $this->paidFromAllocations() > 0;

        if ($hasCompletedPayment) {
            throw new RuntimeException('Cannot cancel an invoice that already has completed payments.');
        }

        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    /**
     * Recompute amount_paid from this invoice's shares of completed payments
     * and promote the status accordingly. Called whenever a payment touching
     * it changes. Idempotent and side-effect-only on the invoice row.
     */
    public function recomputeStatus(): void
    {
        // Cancelled invoices stay cancelled even if a phantom payment lands.
        if ($this->isCancelled() || $this->isDraft()) {
            return;
        }

        // A payment can be shared across invoices: count only this invoice's share.
        $paid = $this->paidFromAllocations();

        $this->amount_paid = $paid;

        if ($paid <= 0) {
            $this->status = $this->isPastDue() ? self::STATUS_OVERDUE : self::STATUS_UNPAID;
            $this->paid_at = null;
        } elseif ($paid < $this->total_amount) {
            // Part-paid but past due still counts as overdue, so it stays on
            // the overdue lists until the balance is cleared.
            $this->status = $this->isPastDue() ? self::STATUS_OVERDUE : self::STATUS_PARTIAL;
            $this->paid_at = null;
        } else {
            $this->status = self::STATUS_PAID;
            $this->paid_at ??= now();
        }

        $this->save();

        // A deposit counts as held once its invoice is fully paid.
        app(DepositLedger::class)->syncInvoice($this);
    }

    /**
     * Recompute invoice money totals from the live items collection. Called
     * by InvoiceItem's saving/deleting hooks so the parent stays consistent.
     */
    public function recomputeTotals(): void
    {
        $subtotal = (int) $this->items()->sum('line_total');

        $this->subtotal = $subtotal;
        $this->total_amount = $subtotal + $this->tax_amount;

        $this->saveQuietly();
    }

    /**
     * Mark an unpaid/partial invoice as overdue when the due date has passed.
     * Returns true if a transition happened.
     */
    public function markOverdueIfDue(): bool
    {
        if (! in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIAL], true)) {
            return false;
        }

        if (! $this->isPastDue()) {
            return false;
        }

        $this->status = self::STATUS_OVERDUE;
        $this->save();

        $this->addLateFee();

        try {
            $renterUser = $this->lease?->renter?->user;
            if ($renterUser) {
                $renterUser->notify(new InvoiceOverdueNotification($this->fresh()));
            }
        } catch (\Throwable $e) {
            Log::warning('InvoiceOverdueNotification failed', [
                'invoice_id' => $this->id, 'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /** Past the due date plus the lease's grace period (none for stays and hand-made invoices). */
    public function isPastDue(): bool
    {
        if (! $this->due_date instanceof Carbon) {
            return false;
        }

        $grace = $this->lease_id !== null ? (int) ($this->lease->grace_days ?? 0) : 0;

        return $this->due_date->copy()->startOfDay()->addDays($grace)->lt(today());
    }

    /**
     * Add the lease's late fee to this invoice, once. Called when it turns
     * overdue; does nothing when the lease has no late fee.
     */
    public function addLateFee(): void
    {
        $lease = $this->lease;
        if ($lease === null || $this->items()->where('type', InvoiceItem::TYPE_LATE_FEE)->exists()) {
            return;
        }

        $fee = $lease->lateFeeFor($this->total_amount);
        if ($fee <= 0) {
            return;
        }

        $this->items()->create([
            'description' => __('Late fee'),
            'type' => InvoiceItem::TYPE_LATE_FEE,
            'quantity' => 1,
            'unit_price' => $fee,
        ]);
        $this->refresh()->recomputeStatus();
    }

    public function balanceDue(): int
    {
        return max(0, $this->total_amount - $this->amount_paid);
    }

    /* ----- Display helpers ----- */

    /** Who the invoice is for: the lease's renter or the booking's guest. */
    public function getBillToNameAttribute(): string
    {
        return $this->lease->renter->display_name
            ?? $this->booking->guest->full_name
            ?? '—';
    }

    public function getUnitCodeAttribute(): ?string
    {
        return $this->lease->unit->code ?? $this->booking->unit->code ?? null;
    }

    public function getFormattedTotalAttribute(): string
    {
        return $this->currency.' '.number_format($this->total_amount / 100, 0, '.', ',');
    }

    public function getFormattedAmountPaidAttribute(): string
    {
        return $this->currency.' '.number_format($this->amount_paid / 100, 0, '.', ',');
    }

    public function getFormattedBalanceAttribute(): string
    {
        return $this->currency.' '.number_format($this->balanceDue() / 100, 0, '.', ',');
    }

    /* ----- Scopes ----- */

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PARTIAL, self::STATUS_OVERDUE]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    /** Renter (lease) or guest (booking) name contains $search. */
    public function scopeBillToMatches(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('lease.renter', fn (Builder $r) => $r
                ->where('full_name', 'ilike', "%{$search}%")
                ->orWhere('business_name', 'ilike', "%{$search}%"))
            ->orWhereHas('booking.guest', fn (Builder $g) => $g->where('full_name', 'ilike', "%{$search}%")));
    }
}
