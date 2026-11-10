<?php

namespace App\Models;

use App\Support\Files;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Propaganistas\LaravelPhone\PhoneNumber;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Throwable;

/**
 * Client = the landlord / property-management company using PMS.
 *
 * Extends stancl/tenancy's base Tenant and is wired in as the tenant_model
 * in config/tenancy.php. We call it "Client" everywhere in our UI, docs,
 * and code; the underlying DB table stays `tenants` and the FK is
 * `tenant_id` because stancl's scoping logic depends on those names.
 *
 * See CLAUDE.md > Naming glossary.
 *
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $limits_waived_until
 * @property bool $is_demo
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
            'icon_path',
            'brand_primary_color',
            'status',
            'plan_id',
            'trial_ends_at',
            'limits_waived_until',
            'settings',
            'is_demo',
        ];
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_demo' => 'boolean',
            'trial_ends_at' => 'datetime',
            'limits_waived_until' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        // FK column on subscriptions is tenant_id (not the default client_id).
        return $this->hasMany(Subscription::class, 'tenant_id');
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

    /** Where workspace logos live: the website shows them, so the public disk. */
    public static function logoDisk(): string
    {
        return Files::publicDisk();
    }

    /**
     * The logo's public address, or null when none is uploaded. No existence
     * check: on B2 that would be a network call on every page.
     */
    public function logoUrl(): ?string
    {
        return Files::publicUrl($this->logo_path);
    }

    /** The square icon for the browser tab and phone home screen, or null when none is uploaded. */
    public function iconUrl(): ?string
    {
        return Files::publicUrl($this->icon_path);
    }

    /**
     * Whether the logo is a wide one (e.g. a name written out) rather than a
     * square mark. Wide logos are shown in full and replace the name beside
     * them. Measured once per logo file and remembered in settings.
     */
    public function logoIsWide(): bool
    {
        $path = $this->logo_path;

        if (! is_string($path) || $path === '') {
            return false;
        }

        $settings = $this->settings ?? [];
        if (($settings['logo_shape_of'] ?? null) === $path) {
            return (bool) ($settings['logo_wide'] ?? false);
        }

        try {
            $size = getimagesizefromstring((string) Storage::disk(self::logoDisk())->get($path));
        } catch (Throwable) {
            $size = false;
        }

        if ($size === false) {
            return false;
        }

        $wide = $size[0] >= 1.6 * max(1, $size[1]);
        $this->forceFill(['settings' => [...$settings, 'logo_shape_of' => $path, 'logo_wide' => $wide]])->saveQuietly();

        return $wide;
    }

    /**
     * A WhatsApp chat link to the workspace's contact phone, with $text
     * already typed in, or null when there is no usable number.
     */
    public function whatsappUrl(?string $text = null): ?string
    {
        try {
            $number = new PhoneNumber(trim((string) $this->contact_phone), 'TZ');

            if (! $number->isValid()) {
                return null;
            }

            $digits = ltrim($number->formatE164(), '+');
        } catch (Throwable) {
            return null;
        }

        return 'https://wa.me/'.$digits.($text !== null && $text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
