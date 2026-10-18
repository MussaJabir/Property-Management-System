<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A 1–5 rating: from a guest about a stay (booking_id) or from a renter
 * about their tenancy (lease_id). Exactly one of the two is set.
 *
 * @property string $source
 * @property string|null $booking_id
 * @property string|null $lease_id
 * @property int $score
 * @property string|null $comment
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
        ];
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
