<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use App\Services\Portal\RenterPortalAccountProvisioner;
use Illuminate\Database\Eloquent\Builder;
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
 *
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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

            $this->unit?->update(['status' => Unit::STATUS_VACANT]);

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

            $this->unit?->update(['status' => Unit::STATUS_VACANT]);

            $this->history()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'action' => 'ended',
                'before' => $before,
                'after' => ['status' => $this->status, 'ended_at' => $endedAt->toIso8601String()],
            ]);
        });
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
