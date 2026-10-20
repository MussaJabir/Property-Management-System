<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A 1–5 rating: from a guest about a stay (booking_id) or from a renter
 * about their tenancy (lease_id). Exactly one of the two is set.
 *
 * @property string $source
 * @property string|null $booking_id
 * @property string|null $lease_id
 * @property int $score
 * @property string|null $comment
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property-read Booking|null $booking
 * @property-read Lease|null $lease
 * @property-read string $from_name
 * @property-read string|null $unit_code
 */
class Rating extends Model
{
    use TenantScopedModel;

    public const SOURCE_GUEST = 'guest';

    public const SOURCE_RENTER = 'renter';

    protected $fillable = [
        'source',
        'booking_id',
        'lease_id',
        'score',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A changed rating needs approving again before it shows publicly.
        static::saving(function (Rating $rating): void {
            if ($rating->exists && $rating->published_at !== null && $rating->isDirty(['score', 'comment']) && ! $rating->isDirty('published_at')) {
                $rating->published_at = null;
            }
        });
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** @param  Builder<Rating>  $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Ratings about a unit: from its guests' stays and from its leases.
     *
     * @param  Builder<Rating>  $query
     */
    public function scopeForUnit(Builder $query, string $unitId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('booking', fn (Builder $b) => $b->where('unit_id', $unitId))
            ->orWhereHas('lease', fn (Builder $l) => $l->where('unit_id', $unitId)));
    }

    /**
     * Average and count of approved ratings per unit, in one query.
     *
     * @param  array<int, string>  $unitIds
     * @return array<string, array{average: float, count: int}>
     */
    public static function summaryForUnits(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        return self::query()
            ->published()
            ->leftJoin('bookings', 'bookings.id', '=', 'ratings.booking_id')
            ->leftJoin('leases', 'leases.id', '=', 'ratings.lease_id')
            ->whereIn(DB::raw('COALESCE(bookings.unit_id, leases.unit_id)'), $unitIds)
            ->groupBy(DB::raw('COALESCE(bookings.unit_id, leases.unit_id)'))
            ->get([DB::raw('COALESCE(bookings.unit_id, leases.unit_id) AS unit_id'), DB::raw('AVG(ratings.score) AS average'), DB::raw('COUNT(*) AS total')])
            ->mapWithKeys(fn (Rating $row): array => [(string) $row->getAttribute('unit_id') => [
                'average' => round((float) $row->getAttribute('average'), 1),
                'count' => (int) $row->getAttribute('total'),
            ]])
            ->all();
    }

    /** First name only on the public website. */
    public function publicName(): string
    {
        $full = $this->booking->guest->full_name ?? $this->lease->renter->full_name ?? '';

        return trim((string) strtok($full, ' ')) ?: __('A guest');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function getFromNameAttribute(): string
    {
        return $this->booking->guest->full_name
            ?? $this->lease->renter->display_name
            ?? '—';
    }

    public function getUnitCodeAttribute(): ?string
    {
        return $this->booking->unit->code ?? $this->lease->unit->code ?? null;
    }

    /** @return array<string, string> */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_GUEST => __('Guest'),
            self::SOURCE_RENTER => __('Renter'),
        ];
    }
}
