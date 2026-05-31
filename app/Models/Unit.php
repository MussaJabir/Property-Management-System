<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unit = anything rentable inside a Property: a room, apartment, business
 * frame, office, shop, warehouse. Status tracks vacancy state.
 *
 * rent_amount is stored as TZS cents (integer). Use ->rent_amount / 100 for
 * display or wire up cknow/laravel-money casts later.
 *
 * @property-read Property|null $property
 */
class Unit extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScopedModel;

    public const TYPE_ROOM = 'room';

    public const TYPE_APARTMENT = 'apartment';

    public const TYPE_BUSINESS_FRAME = 'business_frame';

    public const TYPE_OFFICE = 'office';

    public const TYPE_SHOP = 'shop';

    public const TYPE_WAREHOUSE = 'warehouse';

    public const STATUS_VACANT = 'vacant';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_RESERVED = 'reserved';

    public const BILLING_MONTHLY = 'monthly';

    public const BILLING_QUARTERLY = 'quarterly';

    public const BILLING_ANNUAL = 'annual';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rent_amount' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'size_sqm' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /* ----- Status helpers ----- */

    public function isVacant(): bool
    {
        return $this->status === self::STATUS_VACANT;
    }

    public function isOccupied(): bool
    {
        return $this->status === self::STATUS_OCCUPIED;
    }

    /* ----- Display helpers ----- */

    public function getFormattedRentAttribute(): string
    {
        $major = $this->rent_amount / 100;

        return $this->rent_currency.' '.number_format($major, 0, '.', ',');
    }

    /* ----- Scopes ----- */

    public function scopeVacant(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VACANT);
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OCCUPIED);
    }

    /**
     * Vacant units for a given client, eager-loading the property and
     * its primary photo. Used by the public landing /{tenant}/units page
     * (Phase 9 CMS) and by the operator dashboard widget.
     */
    public static function vacantForClient(string $clientId): Builder
    {
        return static::query()
            ->where('tenant_id', $clientId)
            ->where('status', self::STATUS_VACANT)
            ->with(['property', 'property.media']);
    }
}
