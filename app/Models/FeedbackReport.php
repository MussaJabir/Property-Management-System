<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message from a client's staff to BJP (problem, idea or question).
 * Scoped to the client inside the operator panel; the super admin panel
 * runs without tenancy and so sees every client's reports.
 *
 * @property string $type
 * @property string $message
 * @property string|null $page_url
 * @property string|null $screenshot_path
 * @property string $status
 * @property string|null $reply
 * @property Carbon|null $resolved_at
 * @property-read User|null $user
 * @property-read Client|null $client
 */
class FeedbackReport extends Model
{
    use TenantScopedModel;

    public const TYPE_PROBLEM = 'problem';

    public const TYPE_IDEA = 'idea';

    public const TYPE_QUESTION = 'question';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'user_id',
        'type',
        'message',
        'page_url',
        'screenshot_path',
        'status',
        'reply',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_PROBLEM => __('Something is wrong'),
            self::TYPE_IDEA => __('Idea or request'),
            self::TYPE_QUESTION => __('Question'),
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_NEW => __('New'),
            self::STATUS_IN_PROGRESS => __('Being looked at'),
            self::STATUS_RESOLVED => __('Resolved'),
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_RESOLVED);
    }
}
