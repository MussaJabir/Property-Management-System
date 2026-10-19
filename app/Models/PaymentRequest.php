<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A mobile money payment asked for through Selcom against an invoice (see
 * the migration). Stays pending until Selcom confirms it, when it records
 * a Payment and moves to completed.
 *
 * @property string $invoice_id
 * @property string $order_id
 * @property string $transid
 * @property int $amount cents
 * @property string $currency
 * @property string $msisdn
 * @property string $status
 * @property string|null $channel
 * @property string|null $selcom_reference
 * @property string|null $selcom_transid
 * @property string|null $payment_id
 * @property int|null $requested_by_user_id
 * @property string|null $failure_reason
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property-read Invoice|null $invoice
 * @property-read Payment|null $payment
 */
class PaymentRequest extends Model
{
    use HasUuids, TenantScopedModel;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'invoice_id',
        'order_id',
        'transid',
        'amount',
        'currency',
        'msisdn',
        'status',
        'channel',
        'selcom_reference',
        'selcom_transid',
        'payment_id',
        'requested_by_user_id',
        'failure_reason',
        'last_checked_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'last_checked_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->order_id ??= self::newReference('PMS');
            $request->transid ??= self::newReference('PMT');
        });
    }

    /**
     * A short unique id for Selcom: a prefix plus 16 upper-case letters and
     * digits, e.g. PMS01JBX3Q7K2M9W4TZ.
     */
    public static function newReference(string $prefix): string
    {
        return $prefix.Str::upper(Str::random(16));
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
