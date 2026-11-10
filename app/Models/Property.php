<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use App\Services\Plans\PlanUsage;
use App\Support\Files;
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
 * Property = a building, compound, or commercial space owned/managed by the
 * client. Contains one or more Units that can be rented out. Photos stored
 * via Spatie MediaLibrary on the default disk (B2 in prod, local in dev).
 */
class Property extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    protected static function booted(): void
    {
        // Plan limit safety net for every create path (panel, imports, API).
        static::creating(function (self $model): void {
            PlanUsage::forClientId($model->tenant_id)?->ensureCanAdd(PlanUsage::PROPERTIES);
        });
    }

    public const TYPE_RESIDENTIAL = 'residential';

    public const TYPE_COMMERCIAL = 'commercial';

    public const TYPE_MIXED = 'mixed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'location_id',
        'name',
        'type',
        'address',
        'description',
        'status',
    ];

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function registerMediaCollections(): void
    {
        // The website shows these, so they go on the public disk.
        $this->addMediaCollection('photos')
            ->useDisk(Files::publicDisk());
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
}
