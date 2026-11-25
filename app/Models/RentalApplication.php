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
use Illuminate\Support\Facades\DB;
use Propaganistas\LaravelPhone\Casts\E164PhoneNumberCast;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Someone asking to rent: their details, supporting documents, staff
 * screening notes and the decision. Approving creates (or links) the renter
 * so a lease can follow.
 *
 * @property string $id
 * @property string|null $unit_id
 * @property string|null $renter_id
 * @property string $applicant_name
 * @property string|null $email
 * @property string|null $id_number
 * @property string|null $employer
 * @property int|null $monthly_income
 * @property Carbon|null $move_in_on
 * @property string $status
 * @property string|null $screening_notes
 * @property string|null $decision_reason
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property-read Unit|null $unit
 * @property-read Renter|null $renter
 */
class RentalApplication extends Model implements HasMedia
{
    use Audited, HasUuids, InteractsWithMedia, SoftDeletes, TenantScopedModel;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::STATUS_RECEIVED];

    protected $fillable = [
        'unit_id', 'renter_id', 'applicant_name', 'phone', 'email', 'id_number', 'employer',
        'monthly_income', 'move_in_on', 'status', 'screening_notes', 'decision_reason',
        'decided_by_user_id', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'phone' => E164PhoneNumberCast::class.':TZ',
            'monthly_income' => 'integer',
            'move_in_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Renter, $this> */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')
            ->useDisk(config('filesystems.default'));
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Accept the applicant. Links the existing renter with the same phone, or
     * creates one from the application's details.
     */
    public function approve(?int $userId = null, ?string $reason = null): Renter
    {
        $this->guardOpen();

        return DB::transaction(function () use ($userId, $reason): Renter {
            $phone = (string) $this->getRawOriginal('phone');
            $renter = Renter::query()->where('phone', $phone)->first()
                ?? Renter::create([
                    'type' => Renter::TYPE_INDIVIDUAL,
                    'full_name' => $this->applicant_name,
                    'phone' => $phone,
                    'email' => $this->email,
                    'nida_number' => $this->id_number,
                    'notes' => $this->screening_notes,
                ]);

            $this->decide(self::STATUS_APPROVED, $userId, $reason);
            $this->renter_id = $renter->id;
            $this->save();

            return $renter;
        });
    }

    public function reject(?int $userId = null, ?string $reason = null): void
    {
        $this->guardOpen();
        $this->decide(self::STATUS_REJECTED, $userId, $reason);
        $this->save();
    }

    public function withdraw(?int $userId = null, ?string $reason = null): void
    {
        $this->guardOpen();
        $this->decide(self::STATUS_WITHDRAWN, $userId, $reason);
        $this->save();
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? ucfirst($this->status);
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_RECEIVED => __('Waiting for a decision'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Not approved'),
            self::STATUS_WITHDRAWN => __('Withdrawn'),
        ];
    }

    private function decide(string $status, ?int $userId, ?string $reason): void
    {
        $this->status = $status;
        $this->decided_by_user_id = $userId;
        $this->decided_at = now();
        $this->decision_reason = $reason;
    }

    private function guardOpen(): void
    {
        if (! $this->isOpen()) {
            throw new RuntimeException("Application {$this->id} was already decided ({$this->status}).");
        }
    }
}
