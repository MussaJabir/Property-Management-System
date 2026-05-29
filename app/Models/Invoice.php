<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use App\Services\InvoiceNumberGenerator;
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
use RuntimeException;

/**
 * Invoice = a billable charge against a Lease for one billing period.
 *
 * Money is in TZS cents. amount_paid is denormalized — kept in sync via
 * Invoice::recomputeStatus(), called by the Payment observer.
 *
 * Status machine:
 *   draft   → issue()   → unpaid          (locks the invoice_number)
 *   draft   → cancel()  → cancelled
 *   any-non-final → cancel() refused if any completed payment exists
 *   unpaid|partial → recomputeStatus()    → partial / paid
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
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 */
class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScopedModel;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

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

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /* ----- State machine ----- */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
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

        $hasCompletedPayment = $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->exists();

        if ($hasCompletedPayment) {
            throw new RuntimeException('Cannot cancel an invoice that already has completed payments.');
        }

        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    /**
     * Recompute amount_paid from the live payments table and promote the
     * status accordingly. Called by the Payment observer on every payment
     * save/delete. Idempotent and side-effect-only on the invoice row.
     */
    public function recomputeStatus(): void
    {
        // Cancelled invoices stay cancelled even if a phantom payment lands.
        if ($this->isCancelled() || $this->isDraft()) {
            return;
        }

        $paid = (int) $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        $this->amount_paid = $paid;

        if ($paid <= 0) {
            $this->status = $this->isPastDue() ? self::STATUS_OVERDUE : self::STATUS_UNPAID;
            $this->paid_at = null;
        } elseif ($paid < $this->total_amount) {
            $this->status = self::STATUS_PARTIAL;
            $this->paid_at = null;
        } else {
            $this->status = self::STATUS_PAID;
            $this->paid_at ??= now();
        }

        $this->save();
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

        return true;
    }

    public function isPastDue(): bool
    {
        return $this->due_date instanceof Carbon
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    public function balanceDue(): int
    {
        return max(0, $this->total_amount - $this->amount_paid);
    }

    /* ----- Display helpers ----- */

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
}
