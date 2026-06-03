<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment a client made to BJP for their PMS subscription. Central (not
 * tenant-scoped) — managed from the super-admin panel. amount_tzs is in
 * minor units (cents).
 */
class SubscriptionPayment extends Model
{
    use HasFactory;

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_MOBILE_MONEY = 'mobile_money';

    public const METHOD_CARD = 'card';

    public const METHOD_OTHER = 'other';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_tzs' => 'integer',
            'paid_at' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'TZS '.number_format($this->amount_tzs / 100, 0, '.', ',');
    }
}
