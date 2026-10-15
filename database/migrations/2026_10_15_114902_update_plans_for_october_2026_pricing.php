<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * October 2026 pricing: each property on a plan carries up to 20 units and
 * 2 staff accounts, and a one-property Basic plan is added below Starter.
 *
 * Only touches installs that already have the seeded plan catalogue, so a
 * fresh or test database is left empty for its own seeders.
 */
return new class extends Migration
{
    /** @var array<string, array<string, mixed>> */
    private const PLANS = [
        'basic' => [
            'name' => 'Basic',
            'price_tzs' => 2_500_000,
            'billing_period' => 'monthly',
            'max_properties' => 1,
            'max_units' => 20,
            'max_operators' => 2,
            'features' => ['cms_website', 'maintenance', 'email_notifications'],
        ],
        'starter' => [
            'price_tzs' => 4_900_000,
            'max_properties' => 3,
            'max_units' => 60,
            'max_operators' => 6,
            'features' => ['renter_portal', 'cms_website', 'maintenance', 'reports', 'email_notifications'],
        ],
        'pro' => [
            'price_tzs' => 14_900_000,
            'max_properties' => 15,
            'max_units' => 300,
            'max_operators' => 30,
            'features' => [
                'renter_portal', 'cms_website', 'maintenance', 'reports', 'custom_roles',
                'email_notifications', 'sms_notifications', 'whatsapp_notifications', 'priority_support',
            ],
        ],
        'enterprise' => [
            'features' => [
                'renter_portal', 'cms_website', 'maintenance', 'reports', 'custom_roles',
                'email_notifications', 'sms_notifications', 'whatsapp_notifications', 'priority_support', 'sla',
            ],
        ],
    ];

    /** @var array<string, array<string, mixed>> */
    private const PREVIOUS = [
        'starter' => [
            'max_properties' => 3,
            'max_units' => 30,
            'max_operators' => 2,
            'features' => ['renter_portal', 'cms_website', 'maintenance', 'email_notifications'],
        ],
        'pro' => [
            'max_properties' => 15,
            'max_units' => 150,
            'max_operators' => 8,
            'features' => ['renter_portal', 'cms_website', 'maintenance', 'reports', 'email_notifications', 'whatsapp_notifications', 'priority_support'],
        ],
        'enterprise' => [
            'features' => ['renter_portal', 'cms_website', 'maintenance', 'reports', 'email_notifications', 'sms_notifications', 'whatsapp_notifications', 'priority_support', 'sla'],
        ],
    ];

    public function up(): void
    {
        if (! DB::table('plans')->where('slug', 'starter')->exists()) {
            return;
        }

        foreach (self::PLANS as $slug => $values) {
            $row = $this->encode($values) + ['updated_at' => now()];

            if (DB::table('plans')->where('slug', $slug)->exists()) {
                DB::table('plans')->where('slug', $slug)->update($row);
            } elseif ($slug === 'basic') {
                DB::table('plans')->insert($row + ['slug' => $slug, 'is_public' => true, 'created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::PREVIOUS as $slug => $values) {
            DB::table('plans')->where('slug', $slug)->update($this->encode($values) + ['updated_at' => now()]);
        }

        $basic = DB::table('plans')->where('slug', 'basic')->value('id');
        $inUse = $basic !== null && (
            DB::table('tenants')->where('plan_id', $basic)->exists()
            || DB::table('subscriptions')->where('plan_id', $basic)->exists()
        );

        if ($basic !== null && ! $inUse) {
            DB::table('plans')->where('id', $basic)->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function encode(array $values): array
    {
        if (isset($values['features'])) {
            $values['features'] = json_encode($values['features']);
        }

        return $values;
    }
};
