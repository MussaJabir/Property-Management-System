<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use App\Services\FrontDesk\ShortStayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * A request sent from a room page on the public website, without signing
 * in: a stay (dates, guests) for a room let by the night, or an enquiry
 * (move-in date) for a room let on a lease.
 *
 *   new → confirm() → confirmed   (stays only: books the room, issues the bill)
 *   new → decline() → declined
 *   new → markHandled() → handled (enquiries: staff called them back)
 *
 * @property string $unit_id
 * @property string $kind
 * @property string $full_name
 * @property string $phone
 * @property Carbon|null $check_in_date
 * @property Carbon|null $check_out_date
 * @property Carbon|null $move_in_date
 * @property int $guests_count
 * @property string|null $message
 * @property string $status
 * @property string|null $booking_id
 * @property Carbon|null $handled_at
 * @property string|null $decline_reason
 * @property Carbon|null $created_at
 * @property-read Unit|null $unit
 * @property-read Booking|null $booking
 */
class BookingRequest extends Model
{
    use HasUuids, SoftDeletes, TenantScopedModel;

    public const KIND_STAY = 'stay';

    public const KIND_ENQUIRY = 'enquiry';

    public const STATUS_NEW = 'new';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_HANDLED = 'handled';

    /** Longest stay that can be requested online. */
    public const MAX_NIGHTS = 60;

    protected $fillable = [
        'unit_id',
        'kind',
        'full_name',
        'phone',
        'check_in_date',
        'check_out_date',
        'move_in_date',
        'guests_count',
        'message',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'move_in_date' => 'date',
            'guests_count' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @param  Builder<BookingRequest>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function isStay(): bool
    {
        return $this->kind === self::KIND_STAY;
    }

    public function nights(): int
    {
        return $this->check_in_date !== null && $this->check_out_date !== null
            ? max(1, (int) $this->check_in_date->diffInDays($this->check_out_date))
            : 0;
    }

    /**
     * Book the room for the requested dates (issues the bill as any front
     * desk booking does). Refused with RoomNotAvailable when the room was
     * taken meanwhile.
     */
    public function confirm(?int $userId = null): Booking
    {
        if (! $this->isOpen() || ! $this->isStay() || $this->unit === null) {
            throw new RuntimeException(__('This request has already been dealt with.'));
        }

        $booking = app(ShortStayService::class)->book(
            $this->unit,
            ['full_name' => $this->full_name, 'phone' => $this->phone],
            $this->check_in_date ?? today(),
            $this->check_out_date ?? today()->addDay(),
            guestsCount: max(1, $this->guests_count),
            notes: __('Requested on the website.'),
        );

        $this->forceFill([
            'status' => self::STATUS_CONFIRMED,
            'booking_id' => $booking->id,
            'handled_by_user_id' => $userId,
            'handled_at' => now(),
        ])->save();

        return $booking;
    }

    public function decline(?string $reason = null, ?int $userId = null): void
    {
        $this->close(self::STATUS_DECLINED, $userId, $reason);
    }

    public function markHandled(?int $userId = null): void
    {
        $this->close(self::STATUS_HANDLED, $userId);
    }

    private function close(string $status, ?int $userId, ?string $reason = null): void
    {
        if (! $this->isOpen()) {
            throw new RuntimeException(__('This request has already been dealt with.'));
        }

        $this->forceFill([
            'status' => $status,
            'decline_reason' => $reason,
            'handled_by_user_id' => $userId,
            'handled_at' => now(),
        ])->save();
    }
}
