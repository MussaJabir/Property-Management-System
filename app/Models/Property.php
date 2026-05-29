<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
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

    public const TYPE_RESIDENTIAL = 'residential';

    public const TYPE_COMMERCIAL = 'commercial';

    public const TYPE_MIXED = 'mixed';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $guarded = [];

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
        $this->addMediaCollection('photos')
            ->useDisk(config('filesystems.default'));
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
}
