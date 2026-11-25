<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A walk-through of a unit at move-in, move-out or any time between: the
 * condition of each area, meter readings, keys, notes and photos. Once
 * completed it is a record and no longer changes.
 *
 * @property string $id
 * @property string $unit_id
 * @property string|null $lease_id
 * @property string $type
 * @property Carbon $inspected_on
 * @property int|null $inspector_user_id
 * @property list<array{area: string, condition: string, note?: string|null}>|null $items
 * @property string|null $electricity_reading
 * @property string|null $water_reading
 * @property int|null $keys_handed
 * @property string|null $notes
 * @property string|null $renter_present
 * @property string $status
 * @property Carbon|null $completed_at
 * @property-read Unit|null $unit
 * @property-read Lease|null $lease
 * @property-read User|null $inspector
 */
class Inspection extends Model implements HasMedia
{
    use Audited, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    public const TYPE_MOVE_IN = 'move_in';

    public const TYPE_MOVE_OUT = 'move_out';

    public const TYPE_ROUTINE = 'routine';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_COMPLETED = 'completed';

    /** value => label */
    public const CONDITIONS = [
        'good' => 'Good',
        'fair' => 'Fair',
        'poor' => 'Poor',
        'damaged' => 'Damaged',
        'missing' => 'Missing',
    ];

    /** Areas offered on a new inspection; staff add or remove as needed. */
    public const DEFAULT_AREAS = [
        'Doors and locks', 'Windows', 'Walls and paint', 'Floors', 'Ceiling',
        'Kitchen', 'Bathroom and toilet', 'Electrical and lights', 'Plumbing and taps', 'Furniture and fittings',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::STATUS_DRAFT];

    protected $fillable = [
        'unit_id', 'lease_id', 'type', 'inspected_on', 'inspector_user_id', 'items', 'electricity_reading',
        'water_reading', 'keys_handed', 'notes', 'renter_present', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'inspected_on' => 'date',
            'items' => 'array',
            'keys_handed' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk(config('filesystems.default'));
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_MOVE_IN => __('Move-in'),
            self::TYPE_MOVE_OUT => __('Move-out'),
            self::TYPE_ROUTINE => __('Routine check'),
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? $this->type;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** Lock the report. */
    public function complete(): void
    {
        if ($this->isCompleted()) {
            throw new RuntimeException(__('This inspection is already completed.'));
        }
        if ($this->items === null || $this->items === []) {
            throw new RuntimeException(__('Record the condition of at least one area first.'));
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Areas in a worse state than at move-in, for a move-out report.
     *
     * @return array<string, array{0: string, 1: string}> area => [then, now]
     */
    public function worseThanMoveIn(): array
    {
        $moveIn = $this->moveInInspection();
        if ($moveIn === null || $this->type !== self::TYPE_MOVE_OUT) {
            return [];
        }

        $rank = array_flip(array_keys(self::CONDITIONS));
        $before = collect($moveIn->items ?? [])->keyBy(fn (array $i): string => mb_strtolower(trim($i['area'])));
        $worse = [];
        foreach ($this->items ?? [] as $item) {
            $was = $before->get(mb_strtolower(trim($item['area'])));
            if ($was !== null && ($rank[$item['condition']] ?? 0) > ($rank[$was['condition']] ?? 0)) {
                $worse[$item['area']] = [self::CONDITIONS[$was['condition']] ?? $was['condition'], self::CONDITIONS[$item['condition']] ?? $item['condition']];
            }
        }

        return $worse;
    }

    /** The completed move-in inspection of the same lease, if any. */
    public function moveInInspection(): ?self
    {
        if ($this->lease_id === null) {
            return null;
        }

        return self::query()
            ->where('lease_id', $this->lease_id)
            ->where('type', self::TYPE_MOVE_IN)
            ->where('status', self::STATUS_COMPLETED)
            ->whereKeyNot($this->getKey())
            ->latest('inspected_on')
            ->first();
    }
}
