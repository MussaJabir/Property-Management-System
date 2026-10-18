<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Booking = a guest's stay in a unit for whole nights: check_in_date up to,
 * not including, check_out_date. Billed by one ordinary Invoice (booking_id),
 * so payments, receipts and overdue checks work as for rent.
 *
 * Status machine (create and extend go through ShortStayService):
 *   reserved   → checkIn()   → checked_in    (unit marked occupied)
 *   checked_in → checkOut()  → checked_out   (unit vacant again)
 *   reserved   → cancel()    → cancelled     (invoice cancelled if unpaid)
 *   reserved   → markNoShow()→ no_show       (invoice kept)
 *
 * Postgres refuses overlapping live bookings on a unit (bookings_no_overlap).
 *
 * @property string $unit_id
 * @property string $guest_id
 * @property Carbon $check_in_date
 * @property Carbon $check_out_date
 * @property int $nightly_rate
 * @property string $currency
 * @property string $status
 * @property int $guests_count
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property Carbon|null $cancelled_at
 * @property-read Unit|null $unit
 * @property-read Guest|null $guest
 * @property-read Invoice|null $invoice
 */
class Booking extends Model
{
    use HasUuids, SoftDeletes, TenantScopedModel;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_CHECKED_OUT = 'checked_out';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    /** Statuses that hold the room (and count for overlap). */
    public const LIVE_STATUSES = [self::STATUS_RESERVED, self::STATUS_CHECKED_IN];

    protected $fillable = [
        'unit_id',
        'guest_id',
        'check_in_date',
        'check_out_date',
        'nightly_rate',
        'currency',
        'status',
        'guests_count',
        'created_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'nightly_rate' => 'integer',
            'guests_count' => 'integer',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }

    public function invoice(): HasOne
    {
        // Not latestOfMany(): it aggregates MAX(id), which Postgres lacks for uuid.
        return $this->hasOne(Invoice::class)->where('status', '!=', Invoice::STATUS_CANCELLED)->latest();
    }

    /* ----- State machine ----- */

    public function nights(): int
    {
        return max(1, (int) $this->check_in_date->diffInDays($this->check_out_date));
    }

    public function isReserved(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE_STATUSES, true);
    }

    /** A reserved stay can be checked in from its first night onwards. */
    public function canCheckIn(): bool
    {
        return $this->isReserved() && ! $this->check_in_date->isFuture();
    }

    public function checkIn(): void
    {
        if (! $this->canCheckIn()) {
            throw new RuntimeException(__('This booking can\'t be checked in now.'));
        }

        DB::transaction(function (): void {
            $this->status = self::STATUS_CHECKED_IN;
            $this->checked_in_at = now();
            $this->save();

            $this->unit?->update(['status' => Unit::STATUS_OCCUPIED]);
        });
    }

    public function checkOut(): void
    {
        if (! $this->isCheckedIn()) {
            throw new RuntimeException(__('Only a guest who is checked in can check out.'));
        }

        DB::transaction(function (): void {
            $this->status = self::STATUS_CHECKED_OUT;
            $this->checked_out_at = now();
            $this->save();

            $this->releaseUnit();
        });
    }

    /** Cancel a reservation. Its invoice is cancelled unless already paid into. */
    public function cancel(): void
    {
        if (! $this->isReserved()) {
            throw new RuntimeException(__('Only a reservation can be cancelled.'));
        }

        DB::transaction(function (): void {
            $this->status = self::STATUS_CANCELLED;
            $this->cancelled_at = now();
            $this->save();

            $invoice = $this->invoice;
            if ($invoice !== null && ! $invoice->payments()->where('status', Payment::STATUS_COMPLETED)->exists()) {
                $invoice->cancel();
            }
        });
    }

    /** The guest never came. The room is freed; the invoice stays. */
    public function markNoShow(): void
    {
        if (! $this->isReserved()) {
            throw new RuntimeException(__('Only a reservation can be marked as no-show.'));
        }

        $this->status = self::STATUS_NO_SHOW;
        $this->save();
    }

    /** Back to vacant, unless a lease or another guest still holds the unit. */
    private function releaseUnit(): void
    {
        $unit = $this->unit;

        if ($unit === null) {
            return;
        }

        $stillUsed = $unit->leases()->where('status', Lease::STATUS_ACTIVE)->exists()
            || $unit->bookings()->where('status', self::STATUS_CHECKED_IN)->whereKeyNot($this->getKey())->exists();

        if (! $stillUsed) {
            $unit->update(['status' => Unit::STATUS_VACANT]);
        }
    }

    /* ----- Display helpers ----- */

    public function getFormattedNightlyRateAttribute(): string
    {
        return $this->currency.' '.number_format($this->nightly_rate / 100, 0, '.', ',');
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_RESERVED => __('Reserved'),
            self::STATUS_CHECKED_IN => __('Checked in'),
            self::STATUS_CHECKED_OUT => __('Checked out'),
            self::STATUS_CANCELLED => __('Cancelled'),
            self::STATUS_NO_SHOW => __('No-show'),
        ];
    }

    /* ----- Scopes ----- */

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', self::LIVE_STATUSES);
    }

    /** Live bookings that share at least one night with [in, out). */
    public function scopeDuring(Builder $query, Carbon $in, Carbon $out): Builder
    {
        return $query->whereIn('status', self::LIVE_STATUSES)
            ->whereDate('check_in_date', '<', $out->toDateString())
            ->whereDate('check_out_date', '>', $in->toDateString());
    }

    /** Live bookings on $unitId that share at least one night with [in, out). */
    public function scopeOverlapping(Builder $query, string $unitId, Carbon $in, Carbon $out): Builder
    {
        return $this->scopeDuring($query->where('unit_id', $unitId), $in, $out);
    }
}
