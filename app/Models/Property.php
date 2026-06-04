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

    protected $fillable = [
        'location_id',
        'name',
        'type',
        'address',
        'description',
        'status',
    ];

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
        // Photos are shown on the public CMS site, so they must live on a
        // web-accessible disk. The B2 disk is also public (signed URLs),
        // so we honour FILESYSTEM_DISK when it is set to b2, otherwise
        // fall back to the `public` disk (storage/app/public — served via
        // the public/storage symlink). Never `local` (private storage).
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
}
