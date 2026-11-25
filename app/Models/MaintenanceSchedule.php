<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Preventive maintenance that repeats (service the generator every 3 months,
 * clean the water tank every year). When due, it opens an approved work order
 * and moves its next date on.
 *
 * @property int $id
 * @property string $unit_id
 * @property string $title
 * @property string|null $description
 * @property string|null $category
 * @property string $priority
 * @property int|null $contractor_id
 * @property int|null $assigned_to_user_id
 * @property int|null $estimated_cost cents
 * @property int $every
 * @property string $period
 * @property Carbon $next_due_on
 * @property Carbon|null $last_created_on
 * @property bool $is_active
 * @property-read Unit|null $unit
 * @property-read Contractor|null $contractor
 */
class MaintenanceSchedule extends Model
{
    use Audited, SoftDeletes, TenantScopedModel;

    /** value => label */
    public const PERIODS = [
        'week' => 'Weeks',
        'month' => 'Months',
        'year' => 'Years',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['priority' => 'medium', 'every' => 1, 'period' => 'month', 'is_active' => true];

    protected $fillable = [
        'unit_id', 'title', 'description', 'category', 'priority', 'contractor_id', 'assigned_to_user_id',
        'estimated_cost', 'every', 'period', 'next_due_on', 'last_created_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'integer',
            'every' => 'integer',
            'next_due_on' => 'date',
            'last_created_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function isDue(?Carbon $on = null): bool
    {
        return $this->is_active && $this->next_due_on->lte(($on ?? today())->copy()->startOfDay());
    }

    /** "Every 3 months" */
    public function frequencyLabel(): string
    {
        $unit = $this->every === 1 ? rtrim(strtolower(self::PERIODS[$this->period] ?? $this->period), 's') : strtolower(self::PERIODS[$this->period] ?? $this->period);

        return $this->every === 1 ? __('Every :unit', ['unit' => $unit]) : __('Every :n :unit', ['n' => $this->every, 'unit' => $unit]);
    }

    /** The date after $from, one interval on. */
    public function nextDateAfter(Carbon $from): Carbon
    {
        return match ($this->period) {
            'week' => $from->copy()->addWeeks($this->every),
            'year' => $from->copy()->addYearsNoOverflow($this->every),
            default => $from->copy()->addMonthsNoOverflow($this->every),
        };
    }

    /**
     * Open the work order for the current due date and move the schedule on.
     * Missed dates are not back-filled: one work order, then the next date
     * after today.
     */
    public function createWorkOrder(): MaintenanceRequest
    {
        return DB::transaction(function (): MaintenanceRequest {
            $dueOn = $this->next_due_on->copy();

            /** @var MaintenanceRequest $request */
            $request = MaintenanceRequest::create([
                'unit_id' => $this->unit_id,
                'maintenance_schedule_id' => $this->id,
                'title' => $this->title,
                'description' => $this->description ?: __('Scheduled maintenance (:frequency).', ['frequency' => $this->frequencyLabel()]),
                'category' => $this->category,
                'priority' => $this->priority,
                'status' => MaintenanceRequest::STATUS_PENDING,
                'reported_at' => now(),
            ]);
            $request->approve(
                contractorId: $this->contractor_id,
                assigneeId: $this->assigned_to_user_id,
                estimatedCents: $this->estimated_cost,
                note: __('Opened by the schedule ":title".', ['title' => $this->title]),
                dueOn: $dueOn->max(today())->toDateString(),
            );

            $next = $this->nextDateAfter($dueOn);
            while ($next->lte(today())) {
                $next = $this->nextDateAfter($next);
            }
            $this->forceFill(['last_created_on' => today(), 'next_due_on' => $next])->save();

            return $request;
        });
    }
}
