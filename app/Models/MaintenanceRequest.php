<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use App\Services\WorkOrderNumberGenerator;
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
 *   pending     → approve()  → approved (gets a work order number)
 *   pending|approved → start() → in_progress (starting a pending request approves it)
 *   in_progress → complete() → completed
 *   completed   → signOff()  → stays completed, stamped as checked and accepted
 *   any non-final → cancel() → cancelled
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
 * @property int|null $estimated_cost cents
 * @property int|null $labour_cost cents
 * @property int|null $materials_cost cents
 * @property string|null $category
 * @property string|null $work_order_number
 * @property Carbon|null $approved_at
 * @property Carbon|null $signed_off_at
 * @property string|null $sign_off_note
 * @property int|null $reported_by_user_id
 * @property int|null $assigned_to_user_id
 * @property int|null $approved_by_user_id
 * @property int|null $contractor_id
 * @property int|null $signed_off_by_user_id
 * @property-read Contractor|null $contractor
 * @property-read Unit|null $unit
 * @property-read User|null $reportedBy
 * @property-read User|null $assignedTo
 */
class MaintenanceRequest extends Model implements HasMedia
{
    use Audited, HasFactory, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Fault types, value => label. */
    public const CATEGORIES = [
        'plumbing' => 'Plumbing',
        'electrical' => 'Electrical',
        'carpentry' => 'Carpentry and doors',
        'painting' => 'Painting and walls',
        'roofing' => 'Roofing and leaks',
        'appliances' => 'Appliances',
        'security' => 'Locks and security',
        'cleaning' => 'Cleaning and pests',
        'other' => 'Other',
    ];

    protected $fillable = [
        'unit_id',
        'reported_by_user_id',
        'assigned_to_user_id',
        'approved_by_user_id',
        'contractor_id',
        'signed_off_by_user_id',
        'category',
        'work_order_number',
        'approved_at',
        'signed_off_at',
        'sign_off_note',
        'estimated_cost',
        'labour_cost',
        'materials_cost',
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
            'approved_at' => 'datetime',
            'signed_off_at' => 'datetime',
            'cost' => 'integer',
            'estimated_cost' => 'integer',
            'labour_cost' => 'integer',
            'materials_cost' => 'integer',
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

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function signedOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_off_by_user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(MaintenanceUpdate::class)->orderByDesc('created_at');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk(config('filesystems.default'));

        // Taken when the job is done, to compare with the "before" photos.
        $this->addMediaCollection('after_photos')
            ->useDisk(config('filesystems.default'));
    }

    /* ----- State machine ----- */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isSignedOff(): bool
    {
        return $this->signed_off_at !== null;
    }

    /**
     * Approve a pending request: it becomes a numbered work order, optionally
     * given to a staff member and/or a contractor, with an estimated cost.
     */
    public function approve(?int $userId = null, ?int $contractorId = null, ?int $assigneeId = null, ?int $estimatedCents = null, ?string $note = null): void
    {
        if (! $this->isPending()) {
            throw new RuntimeException("Request {$this->id} cannot be approved from status {$this->status}");
        }

        DB::transaction(function () use ($userId, $contractorId, $assigneeId, $estimatedCents, $note): void {
            $this->markApproved($userId);
            if ($contractorId !== null) {
                $this->contractor_id = $contractorId;
            }
            if ($assigneeId !== null) {
                $this->assigned_to_user_id = $assigneeId;
            }
            if ($estimatedCents !== null) {
                $this->estimated_cost = $estimatedCents;
            }
            $this->save();

            $this->updates()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'note' => $note ?: "Approved as work order {$this->work_order_number}.",
                'status_change' => self::STATUS_APPROVED,
            ]);
        });
    }

    /** Status, approver and work order number; the caller saves. */
    private function markApproved(?int $userId): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approved_at = now();
        $this->approved_by_user_id = $userId;
        $this->work_order_number ??= app(WorkOrderNumberGenerator::class)->next((string) $this->tenant_id);
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
        if (! $this->isPending() && ! $this->isApproved()) {
            throw new RuntimeException("Request {$this->id} cannot be started from status {$this->status}");
        }

        DB::transaction(function () use ($userId, $assigneeId, $note): void {
            if ($this->isPending()) {
                // Starting straight away counts as approving it.
                $this->markApproved($userId);
            }
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
     * Close an in-progress request. Amounts in cents. When labour and/or
     * materials are given without a total, the actual cost is their sum.
     */
    public function complete(?int $userId = null, ?int $costCents = null, ?string $note = null, ?int $labourCents = null, ?int $materialsCents = null): void
    {
        if (! $this->isInProgress()) {
            throw new RuntimeException("Request {$this->id} cannot be completed from status {$this->status}");
        }

        DB::transaction(function () use ($userId, $costCents, $note, $labourCents, $materialsCents): void {
            $this->status = self::STATUS_COMPLETED;
            $this->completed_at = now();
            if ($labourCents !== null) {
                $this->labour_cost = $labourCents;
            }
            if ($materialsCents !== null) {
                $this->materials_cost = $materialsCents;
            }
            if ($costCents === null && ($labourCents !== null || $materialsCents !== null)) {
                $costCents = (int) $labourCents + (int) $materialsCents;
            }
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
     * A manager (or the tenant, through staff) checks the finished work and
     * accepts it. Only completed work can be signed off, once.
     */
    public function signOff(?int $userId = null, ?string $note = null): void
    {
        if (! $this->isCompleted()) {
            throw new RuntimeException("Request {$this->id} cannot be signed off from status {$this->status}");
        }
        if ($this->isSignedOff()) {
            throw new RuntimeException("Request {$this->id} is already signed off.");
        }

        DB::transaction(function () use ($userId, $note): void {
            $this->signed_off_at = now();
            $this->signed_off_by_user_id = $userId;
            $this->sign_off_note = $note;
            $this->save();

            $this->updates()->create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $userId,
                'note' => $note ?: 'Work checked and signed off.',
                'status_change' => null,
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
        return self::money($this->cost);
    }

    public function getFormattedEstimatedCostAttribute(): ?string
    {
        return self::money($this->estimated_cost);
    }

    /** Actual minus estimate, in cents; null until both are known. */
    public function costVariance(): ?int
    {
        if ($this->cost === null || $this->estimated_cost === null) {
            return null;
        }

        return $this->cost - $this->estimated_cost;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category ?? ''] ?? __('Not set');
    }

    public static function money(?int $cents): ?string
    {
        return $cents === null ? null : 'TZS '.number_format($cents / 100, 0, '.', ',');
    }

    /* ----- Scopes ----- */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_IN_PROGRESS]);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }
}
