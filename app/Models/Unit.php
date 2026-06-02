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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Unit = anything rentable inside a Property: a room, apartment, business
 * frame, office, shop, warehouse. Status tracks vacancy state.
 *
 * rent_amount is stored as TZS cents (integer). Use ->rent_amount / 100 for
 * display or wire up cknow/laravel-money casts later.
 *
 * Each unit can carry its own gallery (`photos` collection). On public
 * surfaces a unit with no photos falls back to its property's photos so a
 * listing is never imageless — see coverImageUrl().
 *
 * @property-read Property|null $property
 */
class Unit extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

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

    /**
     * Amenity catalog. Keys are stored in the `amenities` jsonb column; labels
     * are translated via lang/{locale}/amenities.php (key => label). Icons are
     * mapped in the amenity-list Blade partial. Curated for the Tanzanian
     * rental market — keep additions here in sync with the lang files.
     */
    public const AMENITIES = [
        'air_conditioning',
        'wifi',
        'parking',
        'water_247',
        'backup_power',
        'security',
        'furnished',
        'hot_water',
        'fitted_kitchen',
        'balcony',
        'cctv',
        'ensuite',
        'garden',
        'servant_quarter',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rent_amount' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'size_sqm' => 'decimal:2',
            'amenities' => 'array',
        ];
    }

    /**
     * Translated amenity labels for the keys this unit has, preserving the
     * catalog order. Unknown/legacy keys are skipped.
     *
     * @return array<string, string> key => translated label
     */
    public function amenityLabels(): array
    {
        $selected = (array) ($this->amenities ?? []);
        $labels = [];

        foreach (self::AMENITIES as $key) {
            if (in_array($key, $selected, true)) {
                $labels[$key] = __('amenities.'.$key);
            }
        }

        return $labels;
    }

    /**
     * Options for a Filament CheckboxList: key => translated label, full catalog.
     *
     * @return array<string, string>
     */
    public static function amenityOptions(): array
    {
        $options = [];
        foreach (self::AMENITIES as $key) {
            $options[$key] = __('amenities.'.$key);
        }

        return $options;
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /* ----- Media ----- */

    public function registerMediaCollections(): void
    {
        // Mirror Property: public-facing gallery, web-accessible disk.
        // B2 when configured, otherwise the global `public` disk (served via
        // the storage:link symlink). Never the private `local` disk.
        $disk = config('filesystems.default') === 'b2' ? 'b2' : 'public';

        $this->addMediaCollection('photos')
            ->useDisk($disk);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->keepOriginalImageFormat()
            ->nonQueued();

        $this->addMediaConversion('card')
            ->width(800)
            ->height(600)
            ->keepOriginalImageFormat()
            ->nonQueued();
    }

    /**
     * Cover image URL for public surfaces. Uses the unit's own first photo
     * when present; otherwise falls back to the parent property's first photo
     * so a listing is never imageless. Returns null when neither has photos
     * (callers render the branded gradient placeholder).
     */
    public function coverImageUrl(string $conversion = ''): ?string
    {
        $own = $this->getFirstMediaUrl('photos', $conversion);
        if ($own !== '') {
            return $own;
        }

        $fromProperty = $this->property?->getFirstMediaUrl('photos', $conversion);

        return $fromProperty !== '' && $fromProperty !== null ? $fromProperty : null;
    }

    /**
     * Whether this unit has any gallery images of its own (not counting the
     * property fallback). Used to decide if the detail-page gallery shows
     * unit photos or the property's.
     */
    public function hasOwnPhotos(): bool
    {
        return $this->getMedia('photos')->isNotEmpty();
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
