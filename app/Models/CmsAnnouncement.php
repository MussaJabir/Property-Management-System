<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * News/announcement post shown on /{tenant}/news. published_at controls
 * visibility on the public site; un-set means draft.
 *
 * @property Carbon|null $published_at
 */
class CmsAnnouncement extends Model
{
    use HasFactory, SoftDeletes, TenantScopedModel;

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $a): void {
            if (! $a->slug) {
                $a->slug = Str::slug($a->title).'-'.Str::lower(Str::random(6));
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }
}
