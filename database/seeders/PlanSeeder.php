<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                // TZS 49,000 / month = 4,900,000 cents
                'price_tzs' => 4_900_000,
                'billing_period' => 'monthly',
                'max_properties' => 3,
                'max_units' => 30,
                'max_operators' => 2,
                'features' => [
                    'cms_pages' => 'Landing + 4 fixed pages',
                    'renter_portal' => 'Included',
                    'support' => 'Email',
                ],
                'is_public' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price_tzs' => 14_900_000, // TZS 149,000 / month
                'billing_period' => 'monthly',
                'max_properties' => 15,
                'max_units' => 150,
                'max_operators' => 8,
                'features' => [
                    'cms_pages' => 'Landing + custom pages',
                    'renter_portal' => 'Included',
                    'support' => 'Priority email + WhatsApp',
                    'reports' => 'All reports + Excel export',
                ],
                'is_public' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price_tzs' => 0, // contact for pricing
                'billing_period' => 'annual',
                'max_properties' => null,
                'max_units' => null,
                'max_operators' => null,
                'features' => [
                    'cms_pages' => 'Custom',
                    'renter_portal' => 'Included',
                    'support' => 'Dedicated account manager',
                    'reports' => 'All + custom reports',
                    'sla' => '99.9% uptime',
                ],
                'is_public' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
