<?php

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string|null $renter_id
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by_user_id
 * @property string|null $reversal_reason
 * @property int|null $received_by_user_id
 * @property-read Invoice|null $invoice
 * @property-read Renter|null $renter
 * @property-read User|null $reversedBy
 * @property-read Receipt|null $receipt
 * @property-read string $method_label
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

    /** Taken back (a bounced cheque, a payment recorded by mistake). Kept, never deleted. */
    public const STATUS_REVERSED = 'reversed';

    public const PROVIDER_MPESA = 'mpesa';

    public const PROVIDER_TIGOPESA = 'tigopesa';

    public const PROVIDER_AIRTELMONEY = 'airtelmoney';

    public const PROVIDER_HALOPESA = 'halopesa';

    /**
     * How to share this payment when it is saved: invoice id => minor units.
     * Not stored. Null shares it oldest first, starting with invoice_id.
     *
     * @var array<string, int>|null
     */
    public ?array $allocationPlan = null;

    protected $fillable = [
        'invoice_id',
        'renter_id',
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
            'reversed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Renter, $this> */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /** Minor units shared out to invoices. */
    public function allocatedAmount(): int
    {
        return (int) $this->allocations()->sum('amount');
    }

    /** Minor units not yet used on any invoice: the payer's credit (0 unless completed). */
    public function unappliedAmount(): int
    {
        return $this->isCompleted() ? max(0, $this->amount - $this->allocatedAmount()) : 0;
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

    /**
     * Mobile money networks as shown to people. Tigo Pesa was rebranded
     * "Mixx by Yas"; the stored value stays `tigopesa`.
     *
     * @return array<string, string>
     */
    public static function providerLabels(): array
    {
        return [
            self::PROVIDER_MPESA => 'M-Pesa',
            self::PROVIDER_TIGOPESA => 'Mixx by Yas',
            self::PROVIDER_AIRTELMONEY => 'Airtel Money',
            self::PROVIDER_HALOPESA => 'Halo Pesa',
        ];
    }

    /** @return array<string, string> */
    public static function methodLabels(): array
    {
        return [
            self::METHOD_CASH => __('Cash'),
            self::METHOD_BANK_TRANSFER => __('Bank transfer'),
            self::METHOD_MOBILE_MONEY => __('Mobile money'),
            self::METHOD_CHEQUE => __('Cheque'),
            self::METHOD_CARD => __('Card'),
        ];
    }

    /** "M-Pesa", "Cash", "Bank transfer"… — the network when it is mobile money. */
    public function getMethodLabelAttribute(): string
    {
        if ($this->method === self::METHOD_MOBILE_MONEY && isset(self::providerLabels()[(string) $this->mobile_money_provider])) {
            return self::providerLabels()[(string) $this->mobile_money_provider];
        }

        return self::methodLabels()[$this->method] ?? __(str_replace('_', ' ', ucfirst((string) $this->method)));
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
