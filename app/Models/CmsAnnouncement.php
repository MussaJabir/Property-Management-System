<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use App\Support\Files;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * News/announcement post shown on /{tenant}/news. published_at controls
 * visibility on the public site; un-set means draft.
 *
 * @property Carbon|null $published_at
 * @property string|null $body
 * @property string|null $excerpt
 * @property string|null $image_path
 */
class CmsAnnouncement extends Model
{
    use HasFactory, SoftDeletes, TenantScopedModel;

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body',
        'image_path',
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

            // The summary on the News list is the post's first line.
            if ($a->isDirty('body') || blank($a->excerpt)) {
                $a->excerpt = Str::limit(self::firstLine($a->body), 160) ?: null;
            }
        });
    }

    /** The post's text as safe HTML. Posts written before formatting existed are plain text. */
    public function bodyHtml(): HtmlString
    {
        $body = (string) $this->body;

        return new HtmlString(self::isRich($body)
            ? RichContentRenderer::make($body)->toHtml()
            : nl2br(e($body)));
    }

    public function imageUrl(): ?string
    {
        return Files::publicUrl($this->image_path);
    }

    /** Shown on the website now, waiting for a later date, or hidden. */
    public function state(): string
    {
        return match (true) {
            $this->published_at === null => 'hidden',
            $this->published_at->isFuture() => 'scheduled',
            default => 'shown',
        };
    }

    private static function isRich(string $body): bool
    {
        return preg_match('/<(p|ul|ol|li|strong|em|a|br|h[1-6])[\s>\/]/i', $body) === 1;
    }

    private static function firstLine(?string $body): string
    {
        $text = self::isRich((string) $body)
            ? RichContentRenderer::make((string) $body)->toText()
            : (string) $body;

        return (string) collect(preg_split('/\R/', $text) ?: [])->map(fn (string $line): string => trim($line))->first(fn (string $line): bool => $line !== '');
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
