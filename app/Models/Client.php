<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Client = the landlord / property-management company using PMS.
 *
 * Extends stancl/tenancy's base Tenant and is wired in as the tenant_model
 * in config/tenancy.php. We call it "Client" everywhere in our UI, docs,
 * and code; the underlying DB table stays `tenants` and the FK is
 * `tenant_id` because stancl's scoping logic depends on those names.
 *
 * See CLAUDE.md > Naming glossary.
 */
class Client extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Set primary key to slug at creation time. config/tenancy.php sets
     * id_generator to null so stancl/tenancy doesn't generate a UUID.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $client): void {
            if (empty($client->getAttribute('id')) && ! empty($client->getAttribute('slug'))) {
                $client->setAttribute('id', $client->getAttribute('slug'));
            }
        });
    }

    /**
     * Columns stancl/tenancy should treat as first-class on the tenants table
     * rather than packing into the `data` JSON column.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'slug',
            'name',
            'contact_email',
            'contact_phone',
            'logo_path',
            'brand_primary_color',
            'status',
            'plan_id',
            'trial_ends_at',
            'settings',
        ];
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->where('status', 'active')->latest('started_at')->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
