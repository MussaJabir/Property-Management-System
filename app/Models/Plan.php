<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Plan extends Model
{
    use HasFactory;

    /**
     * Canonical catalogue of plan features (key => human label).
     *
     * Features are currently descriptive only — they label what a plan includes
     * (e.g. on the pricing page); nothing in the app gates behaviour on them yet.
     * `features` is stored as a flat list of these keys, e.g. ['renter_portal',
     * 'reports']. Add new features here; remove keys here that should retire.
     */
    public const FEATURES = [
        'renter_portal' => 'Renter portal',
        'cms_website' => 'Website / CMS pages',
        'maintenance' => 'Maintenance tracking',
        'short_stays' => 'Short stays & front desk',
        'reports' => 'Reports & Excel export',
        'custom_roles' => 'Custom staff roles & permissions',
        'email_notifications' => 'Email notifications',
        'sms_notifications' => 'SMS rent reminders',
        'whatsapp_notifications' => 'WhatsApp rent reminders',
        'priority_support' => 'Priority support',
        'sla' => 'Uptime SLA',
    ];

    /**
     * Catalogue features that are sold on a plan but not built yet. The public
     * pricing page marks them "coming soon" instead of presenting them as live.
     */
    public const COMING_SOON = [
        'sms_notifications',
        'whatsapp_notifications',
    ];

    protected $fillable = [
        'name',
        'slug',
        'price_tzs',
        'billing_period',
        'max_properties',
        'max_units',
        'max_operators',
        'features',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'price_tzs' => 'integer',
            'max_properties' => 'integer',
            'max_units' => 'integer',
            'max_operators' => 'integer',
            'features' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Price formatted for display ("TZS 49,000").
     *
     * Stored as minor units (cents). Divide by 100 for display.
     */
    public function getFormattedPriceAttribute(): string
    {
        $major = $this->price_tzs / 100;

        return 'TZS '.number_format($major, 0, '.', ',');
    }

    /**
     * Human labels for the plan's selected features, in catalogue order.
     * Unknown/retired keys are dropped so display never breaks.
     *
     * @return list<string>
     */
    public function featureLabels(): array
    {
        $selected = (array) ($this->features ?? []);

        return array_values(array_intersect_key(
            self::FEATURES,
            array_flip($selected),
        ));
    }

    /**
     * The plan's selected feature keys, in catalogue order, unknown keys dropped.
     *
     * @return list<string>
     */
    public function featureKeys(): array
    {
        return array_keys(array_intersect_key(
            self::FEATURES,
            array_flip((array) ($this->features ?? [])),
        ));
    }

    /**
     * Plans shown on the public pricing section: public only, sized plans by
     * price, then unlimited (enterprise-style) plans last.
     *
     * @return Collection<int, Plan>
     */
    public static function publicCatalogue(): Collection
    {
        return self::query()
            ->where('is_public', true)
            ->get()
            ->sortBy(fn (Plan $plan): array => [$plan->max_units === null ? 1 : 0, $plan->price_tzs])
            ->values()
            ->toBase();
    }
}
