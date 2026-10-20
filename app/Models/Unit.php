<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use App\Services\Plans\PlanUsage;
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
 * rental_mode says how it is let: long_term (leases), short_stay (bookings
 * by the night at nightly_rate) or both.
 *
 * @property string $rental_mode
 * @property int|null $nightly_rate
 * @property-read Property|null $property
 */
class Unit extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    protected static function booted(): void
    {
        // Plan limit safety net for every create path (panel, imports, API).
        static::creating(function (self $model): void {
            PlanUsage::forClientId($model->tenant_id)?->ensureCanAdd(PlanUsage::UNITS);
        });
    }

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

    public const RENTAL_LONG_TERM = 'long_term';

    public const RENTAL_SHORT_STAY = 'short_stay';

    public const RENTAL_BOTH = 'both';

    public const BILLING_MONTHLY = 'monthly';

    public const BILLING_QUARTERLY = 'quarterly';

    public const BILLING_SEMI_ANNUAL = 'semi_annual';

    public const BILLING_ANNUAL = 'annual';

    public const BILLING_CUSTOM = 'custom';

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

    protected $fillable = [
        'property_id',
        'code',
        'type',
        'rent_amount',
        'rent_currency',
        'billing_cycle',
        'billing_cycle_months',
        'status',
        'rental_mode',
        'nightly_rate',
        'bedrooms',
        'bathrooms',
        'size_sqm',
        'description',
        'amenities',
    ];

    protected function casts(): array
    {
        return [
            'rent_amount' => 'integer',
            'nightly_rate' => 'integer',
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Can be booked by the night (short stays). */
    public function takesShortStays(): bool
    {
        return in_array($this->rental_mode, [self::RENTAL_SHORT_STAY, self::RENTAL_BOTH], true);
    }

    /** @return array<string, string> */
    public static function rentalModeOptions(): array
    {
        return [
            self::RENTAL_LONG_TERM => __('Long-term (leases)'),
            self::RENTAL_SHORT_STAY => __('Short stays (by the night)'),
            self::RENTAL_BOTH => __('Both'),
        ];
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
            ->keepOriginalImageFormat()
            ->nonQueued()
            ->width(400)
            ->height(300);

        $this->addMediaConversion('card')
            ->keepOriginalImageFormat()
            ->nonQueued()
            ->width(800)
            ->height(600);
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

    public function getFormattedNightlyRateAttribute(): string
    {
        return $this->rent_currency.' '.number_format(((int) $this->nightly_rate) / 100, 0, '.', ',');
    }

    public function getFormattedRentAttribute(): string
    {
        $major = $this->rent_amount / 100;

        return $this->rent_currency.' '.number_format($major, 0, '.', ',');
    }

    /**
     * Prices shown on the public website: the nightly rate for rooms let
     * by the night, the rent for rooms let on a lease, both for "both".
     *
     * @return list<array{amount: string, currency: string, per: string}>
     */
    public function publicPrices(): array
    {
        $prices = [];

        if ($this->takesShortStays() && (int) $this->nightly_rate > 0) {
            $prices[] = [
                'amount' => number_format(((int) $this->nightly_rate) / 100, 0, '.', ','),
                'currency' => $this->rent_currency,
                'per' => __('night'),
            ];
        }

        if ($this->rental_mode !== self::RENTAL_SHORT_STAY) {
            $prices[] = [
                'amount' => number_format($this->rent_amount / 100, 0, '.', ','),
                'currency' => $this->rent_currency,
                'per' => $this->rentPeriodLabel(),
            ];
        }

        return $prices;
    }

    /** "Room 4 · Kariakoo Heights" on the public website. */
    public function publicTitle(): string
    {
        return $this->property !== null ? $this->code.' · '.$this->property->name : $this->code;
    }

    /** How the room is let, as a short badge: "Short stay", "Monthly"… */
    public function publicTag(): string
    {
        return match ($this->rental_mode) {
            self::RENTAL_SHORT_STAY => __('Short stay'),
            self::RENTAL_BOTH => __('Nightly or monthly'),
            default => $this->rentPeriodLabel() === __('month') ? __('Monthly') : __('Long term'),
        };
    }

    /**
     * Key facts for room cards and pages: bedrooms, bathrooms, size.
     *
     * @return list<string>
     */
    public function publicFacts(): array
    {
        return array_values(array_filter([
            $this->bedrooms ? trans_choice(':count bedroom|:count bedrooms', (int) $this->bedrooms) : null,
            $this->bathrooms ? trans_choice(':count bathroom|:count bathrooms', (int) $this->bathrooms) : null,
            $this->size_sqm ? rtrim(rtrim(number_format((float) $this->size_sqm, 2, '.', ','), '0'), '.').' m²' : null,
        ]));
    }

    /** What one rent payment covers: "month", "6 months", "year"… */
    public function rentPeriodLabel(): string
    {
        return match ($this->billing_cycle) {
            self::BILLING_QUARTERLY => __('3 months'),
            self::BILLING_SEMI_ANNUAL => __('6 months'),
            self::BILLING_ANNUAL => __('year'),
            self::BILLING_CUSTOM => __(':count months', ['count' => (int) $this->billing_cycle_months]),
            default => __('month'),
        };
    }

    /** Shown on the public website: see scopeListedPublicly(). */
    public function isListedPublicly(): bool
    {
        return self::query()->whereKey($this->getKey())->listedPublicly()->exists();
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
     * Units the public website offers: vacant ones, plus rooms let by the
     * night that have a guest in tonight — they can still be booked for
     * other dates. A room on an active lease is never offered.
     */
    public function scopeListedPublicly(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('status', self::STATUS_VACANT)
            ->orWhere(fn (Builder $stay) => $stay
                ->where('status', self::STATUS_OCCUPIED)
                ->whereIn('rental_mode', [self::RENTAL_SHORT_STAY, self::RENTAL_BOTH])
                ->whereDoesntHave('leases', fn (Builder $lease) => $lease->where('status', Lease::STATUS_ACTIVE))));
    }

    /** Units that can be let on a lease (long-term or both). */
    public function scopeLongTerm(Builder $query): Builder
    {
        return $query->whereIn('rental_mode', [self::RENTAL_LONG_TERM, self::RENTAL_BOTH]);
    }

    /** Units that can be booked by the night (short stay or both). */
    public function scopeShortStay(Builder $query): Builder
    {
        return $query->whereIn('rental_mode', [self::RENTAL_SHORT_STAY, self::RENTAL_BOTH]);
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
