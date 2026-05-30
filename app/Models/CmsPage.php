<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Editable page on the client's public site. Each client has a fixed set of
 * pages (home/about/units/news/contact) seeded on Client creation.
 *
 * `blocks` is a JSON array of `{ type: string, data: array }` records, edited
 * by the operator via the Filament Builder field. Rendering is handled by
 * resources/views/components/cms/block.blade.php which dispatches on type.
 *
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property array<int, array{type: string, data: array<string, mixed>}>|null $blocks
 */
class CmsPage extends Model
{
    use HasFactory, SoftDeletes, TenantScopedModel;

    public const SLUG_HOME = 'home';

    public const SLUG_ABOUT = 'about';

    public const SLUG_UNITS = 'units';

    public const SLUG_NEWS = 'news';

    public const SLUG_CONTACT = 'contact';

    /**
     * Pages every client gets, in nav order.
     *
     * @var array<int, string>
     */
    public const ALL_SLUGS = [
        self::SLUG_HOME,
        self::SLUG_ABOUT,
        self::SLUG_UNITS,
        self::SLUG_NEWS,
        self::SLUG_CONTACT,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }
}
