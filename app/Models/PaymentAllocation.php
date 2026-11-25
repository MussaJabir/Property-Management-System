<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of a payment went to one invoice (minor units). A payment's
 * allocations never add up to more than the payment; what is left over is
 * the payer's credit. Created through App\Services\Payments\PaymentAllocator.
 *
 * @property int $amount
 */
class PaymentAllocation extends Model
{
    use TenantScopedModel;

    protected $fillable = ['payment_id', 'invoice_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
