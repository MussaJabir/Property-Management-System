<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use App\Services\Portal\RenterPortalAccountProvisioner;
use App\Services\RentInvoiceGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Throwable;

/**
 * Lease = renter ↔ unit contract for a span of time. Money columns store
 * minor units (TZS cents). billing_cycle mirrors Unit's expanded enum.
 *
 * State transitions go through activate() / terminate() / end() which
 * (1) update the lease status,
 * (2) update the linked unit's status,
 * (3) write a LeaseHistory row.
 * Activation also issues the first period's invoice (RentInvoiceGenerator).
 *
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property-read Renter|null $renter
 * @property-read Unit|null $unit
 */
class Lease extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_TERMINATED = 'terminated';

    public const BILLING_MONTHLY = 'monthly';

    public const BILLING_QUARTERLY = 'quarterly';

    public const BILLING_SEMI_ANNUAL = 'semi_annual';

    public const BILLING_ANNUAL = 'annual';

    public const BILLING_CUSTOM = 'custom';

    public const LATE_FEE_FIXED = 'fixed';

    public const LATE_FEE_PERCENT = 'percent';

    /** Days before the end date when staff are reminded. */
    public const ENDING_REMINDER_DAYS = [90, 60, 30];

    protected $fillable = [
        'renter_id',
        'unit_id',
        'start_date',
        'end_date',
        'rent_amount',
        'currency',
        'deposit_amount',
        'billing_cycle',
        'billing_cycle_months',
        'payment_due_day',
        'status',
        'terms_notes',
        'grace_days',
        'notice_days',
        'late_fee_type',
        'late_fee_value',
        'increase_percent',
        'increase_every_months',
        'last_increase_on',
        'renewed_from_id',
        'deposit_carried_over',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'integer',
            'deposit_amount' => 'integer',
            'billing_cycle_months' => 'integer',
            'payment_due_day' => 'integer',
            'grace_days' => 'integer',
            'notice_days' => 'integer',
            'late_fee_value' => 'integer',
            'increase_percent' => 'decimal:2',
            'increase_every_months' => 'integer',
            'last_increase_on' => 'date',
            'deposit_carried_over' => 'integer',
            'activated_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(LeaseHistory::class)->orderByDesc('created_at');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return BelongsTo<Lease, $this> */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /** @return HasOne<Lease, $this> */
    public function renewal(): HasOne
    {
        return $this->hasOne(self::class, 'renewed_from_id')
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_ACTIVE]);
    }

    public function registerMediaCollections(): void
    {
        // Lease PDF (signed contract) lives here. Single-file collection — a
        // fresh generation replaces the previous PDF so the latest is always
        // canonical.
        $this->addMediaCollection('contract')
            ->singleFile()
            ->useDisk(config('filesystems.default'));
    }

    /* ----- State machine ----- */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_ENDED, self::STATUS_TERMINATED], true);
    }

    /**
     * Move a pending lease to active. Marks the unit occupied and records
     * the transition in lease_history. Throws if the lease isn't pending.
     */
    public function activate(?int $userId = null): void
    {
        if (! $this->isPending()) {
            throw new RuntimeException("Lease {$this->id} cannot be activated from status {$this->status}");
        }

        // One tenant per unit at a time: never two active leases whose dates overlap.
        $clash = $this->overlappingActiveLease();
        if ($clash !== null) {
            throw new RuntimeException(__(':unit is already let to :renter until :date. End that lease first, or start this one after it.', [
                'unit' => $this->unit !== null ? $this->unit->code : __('This unit'),
                'renter' => $clash->renter !== null ? $clash->renter->display_name : __('another renter'),
                'date' => $clash->end_date?->format('d/m/Y') ?? __('no end date'),
            ]));
        }

        DB::transaction(function () use ($userId): void {
            $before = ['status' => $this->status];
            $activatedAt = now();

            $this->status = self::STATUS_ACTIVE;
            $this->activated_at = $activatedAt;
            $this->save();

            $this->unit?->update(['status' => Unit::STATUS_OCCUPIED]);

            $this->history()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'action' => 'activated',
                'before' => $before,
                'after' => ['status' => $this->status, 'activated_at' => $activatedAt->toIso8601String()],
            ]);

            if ($this->renter) {
                try {
                    app(RenterPortalAccountProvisioner::class)->provisionFor($this->renter);
                } catch (Throwable $e) {
                    // Provisioning shouldn't block activation; the operator
                    // can resend credentials from the renter's profile later.
                    Log::warning(
                        'Renter portal provisioning failed during lease activation',
                        ['lease_id' => $this->id, 'error' => $e->getMessage()],
                    );
                }
            }
        });

        // Bill the first period (rent + deposit) straight away instead of
        // waiting for tomorrow's run. Never blocks the activation.
        if (RentInvoiceGenerator::enabledFor(Client::find($this->tenant_id))) {
            try {
                app(RentInvoiceGenerator::class)->generateFor($this);
            } catch (Throwable $e) {
                Log::warning(
                    'First rent invoice failed during lease activation',
                    ['lease_id' => $this->id, 'error' => $e->getMessage()],
                );
            }
        }
    }

    /**
     * Early termination (renter or operator initiated). Frees the unit.
     */
    public function terminate(?string $reason = null, ?int $userId = null): void
    {
        if (! $this->isActive()) {
            throw new RuntimeException("Lease {$this->id} cannot be terminated from status {$this->status}");
        }

        DB::transaction(function () use ($reason, $userId): void {
            $before = ['status' => $this->status];
            $endedAt = now();

            $this->status = self::STATUS_TERMINATED;
            $this->ended_at = $endedAt;
            $this->save();

            $this->freeUnitIfEmpty();

            $this->history()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'action' => 'terminated',
                'before' => $before,
                'after' => ['status' => $this->status, 'ended_at' => $endedAt->toIso8601String()],
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Natural end of lease at its end_date. Same DB effect as terminate but
     * a different status so reports can distinguish planned vs early exit.
     */
    public function end(?int $userId = null): void
    {
        if (! $this->isActive()) {
            throw new RuntimeException("Lease {$this->id} cannot be ended from status {$this->status}");
        }

        DB::transaction(function () use ($userId): void {
            $before = ['status' => $this->status];
            $endedAt = now();

            $this->status = self::STATUS_ENDED;
            $this->ended_at = $endedAt;
            $this->save();

            $this->freeUnitIfEmpty();

            $this->history()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'action' => 'ended',
                'before' => $before,
                'after' => ['status' => $this->status, 'ended_at' => $endedAt->toIso8601String()],
            ]);
        });
    }

    /**
     * Another active lease on the same unit whose dates overlap this one's.
     * An open-ended lease (no end date) runs forever.
     */
    public function overlappingActiveLease(): ?self
    {
        if ($this->start_date === null) {
            return null;
        }

        return self::query()
            ->with('renter')
            ->where('unit_id', $this->unit_id)
            ->where('status', self::STATUS_ACTIVE)
            ->whereKeyNot($this->getKey())
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $this->start_date->toDateString()))
            ->when($this->end_date !== null, fn (Builder $q) => $q->whereDate('start_date', '<=', $this->end_date->toDateString()))
            ->first();
    }

    /** The unit is vacant again unless another lease (e.g. its renewal) is still active on it. */
    private function freeUnitIfEmpty(): void
    {
        $stillLet = self::query()
            ->where('unit_id', $this->unit_id)
            ->where('status', self::STATUS_ACTIVE)
            ->whereKeyNot($this->getKey())
            ->exists();

        if (! $stillLet) {
            $this->unit?->update(['status' => Unit::STATUS_VACANT]);
        }
    }

    /* ----- Rent increases ----- */

    /** Date of the next yearly (or other) rent increase, or null when the lease has none. */
    public function nextIncreaseOn(): ?Carbon
    {
        if ($this->increase_percent === null || (float) $this->increase_percent <= 0 || $this->start_date === null) {
            return null;
        }

        $every = max(1, (int) ($this->increase_every_months ?: 12));

        return ($this->last_increase_on ?? $this->start_date)->copy()->addMonthsNoOverflow($every);
    }

    /** Rent after the next increase, in minor units, rounded to a whole currency unit. */
    public function rentAfterIncrease(?int $rent = null): int
    {
        $rent ??= $this->rent_amount;
        $raised = $rent * (1 + (float) $this->increase_percent / 100);

        return (int) (round($raised / 100) * 100);
    }

    /**
     * Apply every increase that has fallen due on or before $asOf, and record
     * each in the lease history. Returns how many were applied.
     */
    public function applyDueIncreases(Carbon $asOf, ?int $userId = null): int
    {
        $applied = 0;

        while (($next = $this->nextIncreaseOn()) !== null && $next->lte($asOf) && $applied < 50) {
            $before = $this->rent_amount;
            $this->rent_amount = $this->rentAfterIncrease();
            $this->last_increase_on = $next;
            $this->save();

            $this->history()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'action' => 'rent_increased',
                'before' => ['rent_amount' => $before],
                'after' => ['rent_amount' => $this->rent_amount, 'from' => $next->toDateString()],
            ]);
            $applied++;
        }

        return $applied;
    }

    /* ----- Late fee ----- */

    /** Late fee for an invoice of $total minor units, or 0 when the lease has none. */
    public function lateFeeFor(int $total): int
    {
        $value = (int) ($this->late_fee_value ?? 0);

        return match ($this->late_fee_type) {
            self::LATE_FEE_FIXED => $value,
            // Basis points: 500 = 5%. Rounded to a whole currency unit.
            self::LATE_FEE_PERCENT => (int) (round($total * $value / 10000 / 100) * 100),
            default => 0,
        };
    }

    /* ----- Renewal ----- */

    /**
     * Create the lease that follows this one: same renter and unit, new dates
     * and rent. The deposit can be carried over so it is not billed again.
     *
     * @param  array{start_date: string, end_date?: string|null, rent_amount: int, deposit_amount: int, carry_deposit: bool, activate: bool}  $terms
     */
    public function renew(array $terms, ?int $userId = null): self
    {
        if (! $this->isActive()) {
            throw new RuntimeException(__('Only an active lease can be renewed.'));
        }

        if ($this->renewal()->exists()) {
            throw new RuntimeException(__('This lease already has a renewal.'));
        }

        $renewal = DB::transaction(function () use ($terms, $userId): self {
            $renewal = new self([
                'renter_id' => $this->renter_id,
                'unit_id' => $this->unit_id,
                'renewed_from_id' => $this->id,
                'start_date' => $terms['start_date'],
                'end_date' => $terms['end_date'] ?? null,
                'rent_amount' => $terms['rent_amount'],
                'currency' => $this->currency,
                'deposit_amount' => $terms['deposit_amount'],
                'deposit_carried_over' => $terms['carry_deposit'] ? min($this->deposit_amount, $terms['deposit_amount']) : 0,
                'billing_cycle' => $this->billing_cycle,
                'billing_cycle_months' => $this->billing_cycle_months,
                'payment_due_day' => $this->payment_due_day,
                'grace_days' => $this->grace_days,
                'notice_days' => $this->notice_days,
                'late_fee_type' => $this->late_fee_type,
                'late_fee_value' => $this->late_fee_value,
                'increase_percent' => $this->increase_percent,
                'increase_every_months' => $this->increase_every_months,
                'terms_notes' => $this->terms_notes,
                'status' => self::STATUS_PENDING,
            ]);
            $renewal->forceFill(['tenant_id' => $this->tenant_id])->save();

            $this->history()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'action' => 'renewed',
                'before' => ['end_date' => $this->end_date?->toDateString()],
                'after' => ['renewal_id' => $renewal->id, 'start_date' => $renewal->start_date?->toDateString()],
            ]);

            return $renewal;
        });

        if ($terms['activate']) {
            $renewal->activate($userId);
        }

        return $renewal;
    }

    /** Whole days until the end date (negative once past), or null for an open-ended lease. */
    public function daysLeft(?Carbon $today = null): ?int
    {
        return $this->end_date === null
            ? null
            : (int) ($today ?? today())->copy()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    /* ----- Display helpers ----- */

    public function getFormattedRentAttribute(): string
    {
        return $this->currency.' '.number_format($this->rent_amount / 100, 0, '.', ',');
    }

    public function getFormattedDepositAttribute(): string
    {
        return $this->currency.' '.number_format($this->deposit_amount / 100, 0, '.', ',');
    }

    public function getBillingCycleLabelAttribute(): string
    {
        return match ($this->billing_cycle) {
            self::BILLING_MONTHLY => 'Monthly',
            self::BILLING_QUARTERLY => 'Every 3 months',
            self::BILLING_SEMI_ANNUAL => 'Every 6 months',
            self::BILLING_ANNUAL => 'Yearly',
            self::BILLING_CUSTOM => 'Every '.($this->billing_cycle_months ?? '?').' months',
            default => ucfirst((string) $this->billing_cycle),
        };
    }

    /**
     * How many months one billing period covers. Returns null for unknown
     * cycles (defensive — UI should never get here in practice).
     */
    public function billingCycleMonths(): ?int
    {
        return match ($this->billing_cycle) {
            self::BILLING_MONTHLY => 1,
            self::BILLING_QUARTERLY => 3,
            self::BILLING_SEMI_ANNUAL => 6,
            self::BILLING_ANNUAL => 12,
            self::BILLING_CUSTOM => $this->billing_cycle_months,
            default => null,
        };
    }

    /* ----- Scopes ----- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [Carbon::today(), Carbon::today()->addDays($days)]);
    }
}
