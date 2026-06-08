<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'reports' => 'Reports & Excel export',
        'email_notifications' => 'Email notifications',
        'sms_notifications' => 'SMS notifications',
        'whatsapp_notifications' => 'WhatsApp notifications',
        'priority_support' => 'Priority support',
        'sla' => 'Uptime SLA',
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
}
