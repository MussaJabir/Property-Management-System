<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Payment = money received against an invoice. Manual entry in v1; the
 * Selcom Pay integration (v2) writes here too via its webhook handler.
 *
 * Observer responsibilities (see PaymentObserver):
 *   - on save (created/updated/restored): invoice.recomputeStatus()
 *   - on delete: invoice.recomputeStatus()
 *   - on first transition into `completed`: create a Receipt row
 *
 * @property int $amount cents
 * @property string $currency
 * @property Carbon|null $payment_date
 * @property string $method
 * @property string|null $mobile_money_provider
 * @property string|null $reference_number
 * @property string|null $transaction_id
 * @property string $status
 * @property int|null $received_by_user_id
 * @property-read Invoice|null $invoice
 * @property-read Receipt|null $receipt
 * @property-read User|null $receivedBy
 */
class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes, TenantScopedModel;

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_CHEQUE = 'cheque';

    public const METHOD_CARD = 'card';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const PROVIDER_MPESA = 'mpesa';

    public const PROVIDER_TIGOPESA = 'tigopesa';

    public const PROVIDER_AIRTELMONEY = 'airtelmoney';

    public const PROVIDER_HALOPESA = 'halopesa';

    protected $fillable = [
        'invoice_id',
        'amount',
        'currency',
        'payment_date',
        'method',
        'reference_number',
        'mobile_money_provider',
        'transaction_id',
        'received_by_user_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'payment_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency.' '.number_format($this->amount / 100, 0, '.', ',');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
