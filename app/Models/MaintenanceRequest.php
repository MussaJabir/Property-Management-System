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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Maintenance issue raised against a unit. May be reported by a renter (via
 * the Phase 8 portal) or by an operator.
 *
 * Strict state machine:
 *   pending     → start()    → in_progress
 *   in_progress → complete() → completed
 *   pending|in_progress → cancel() → cancelled
 *
 * Each transition writes a MaintenanceUpdate row (audit timeline).
 *
 * @property string $title
 * @property string $description
 * @property string $priority
 * @property string $status
 * @property Carbon $reported_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $cost cents
 * @property int|null $reported_by_user_id
 * @property int|null $assigned_to_user_id
 * @property-read Unit|null $unit
 * @property-read User|null $reportedBy
 * @property-read User|null $assignedTo
 */
class MaintenanceRequest extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'unit_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'title',
        'description',
        'priority',
        'status',
        'reported_at',
        'started_at',
        'completed_at',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cost' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(MaintenanceUpdate::class)->orderByDesc('created_at');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk(config('filesystems.default'));
    }

    /* ----- State machine ----- */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    /**
     * Move a pending request into in_progress and stamp started_at.
     * Optional assignee + note recorded on the audit row.
     */
    public function start(?int $userId = null, ?int $assigneeId = null, ?string $note = null): void
    {
        if (! $this->isPending()) {
            throw new RuntimeException("Request {$this->id} cannot be started from status {$this->status}");
        }

        DB::transaction(function () use ($userId, $assigneeId, $note): void {
            $this->status = self::STATUS_IN_PROGRESS;
            $this->started_at = now();
            if ($assigneeId !== null) {
                $this->assigned_to_user_id = $assigneeId;
            }
            $this->save();

            $this->updates()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'note' => $note ?: 'Work started.',
                'status_change' => self::STATUS_IN_PROGRESS,
            ]);
        });
    }

    /**
     * Close an in-progress request. Cost in TZS cents — stamped on the request
     * row so it shows in expense-like reports without joining elsewhere.
     */
    public function complete(?int $userId = null, ?int $costCents = null, ?string $note = null): void
    {
        if (! $this->isInProgress()) {
            throw new RuntimeException("Request {$this->id} cannot be completed from status {$this->status}");
        }

        DB::transaction(function () use ($userId, $costCents, $note): void {
            $this->status = self::STATUS_COMPLETED;
            $this->completed_at = now();
            if ($costCents !== null) {
                $this->cost = $costCents;
            }
            $this->save();

            $this->updates()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'note' => $note ?: 'Work completed.',
                'status_change' => self::STATUS_COMPLETED,
            ]);
        });
    }

    /**
     * Cancel a non-final request. Allowed from pending or in_progress.
     */
    public function cancel(?int $userId = null, ?string $reason = null): void
    {
        if ($this->isFinished()) {
            throw new RuntimeException("Request {$this->id} is already finished ({$this->status}); cannot cancel.");
        }

        DB::transaction(function () use ($userId, $reason): void {
            $this->status = self::STATUS_CANCELLED;
            $this->completed_at = now();
            $this->save();

            $this->updates()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'note' => $reason ?: 'Request cancelled.',
                'status_change' => self::STATUS_CANCELLED,
            ]);
        });
    }

    /**
     * Free-text note added by the operator (no status change).
     */
    public function addNote(string $note, ?int $userId = null): MaintenanceUpdate
    {
        /** @var MaintenanceUpdate $update */
        $update = $this->updates()->create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $userId,
            'note' => $note,
            'status_change' => null,
        ]);

        return $update;
    }

    /* ----- Display helpers ----- */

    public function getFormattedCostAttribute(): ?string
    {
        if ($this->cost === null) {
            return null;
        }

        return 'TZS '.number_format($this->cost / 100, 0, '.', ',');
    }

    /* ----- Scopes ----- */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }
}
