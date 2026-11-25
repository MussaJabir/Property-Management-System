<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\TenantScopedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One movement of a lease's deposit. Amounts are positive; the type says
 * which way it went. Created through App\Services\Deposits\DepositLedger.
 *
 * @property string $type
 * @property int $amount
 * @property Carbon|null $occurred_on
 * @property string|null $reason
 */
class DepositTransaction extends Model
{
    use Audited, TenantScopedModel;

    public const TYPE_RECEIVED = 'received';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_REFUND = 'refund';

    /** Moved over from the lease this one renews. */
    public const TYPE_CARRIED_IN = 'carried_in';

    /** Moved on to this lease's renewal. */
    public const TYPE_CARRIED_OUT = 'carried_out';

    /** Types that add to what is held. */
    public const INCOMING = [self::TYPE_RECEIVED, self::TYPE_CARRIED_IN];

    protected $fillable = ['lease_id', 'type', 'amount', 'occurred_on', 'reason', 'invoice_id', 'recorded_by_user_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_on' => 'date'];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** + for money in, − for money out of the deposit. */
    public function signedAmount(): int
    {
        return in_array($this->type, self::INCOMING, true) ? $this->amount : -$this->amount;
    }

    public function label(): string
    {
        return match ($this->type) {
            self::TYPE_RECEIVED => __('Received'),
            self::TYPE_DEDUCTION => __('Deducted'),
            self::TYPE_REFUND => __('Refunded'),
            self::TYPE_CARRIED_IN => __('Carried over from the previous lease'),
            self::TYPE_CARRIED_OUT => __('Carried over to the renewal'),
            default => ucfirst($this->type),
        };
    }
}
