<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    // Central (not tenant-scoped) — managed only from the super-admin panel,
    // so tenant_id is set explicitly here (no BelongsToTenant auto-fill).
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'started_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        // FK column is tenant_id (not the default client_id).
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Number of months one billing cycle of the plan covers. Used when a
     * payment extends the subscription period.
     */
    public function billingMonths(): int
    {
        return match ($this->plan?->billing_period) {
            'quarterly' => 3,
            'semi_annual' => 6,
            'annual', 'yearly' => 12,
            default => 1, // monthly
        };
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
