<?php

use App\Models\Plan;
use Database\Seeders\PlanSeeder;

it('formats price as TZS with thousands separator', function () {
    $plan = Plan::create([
        'name' => 'Pro Plan',
        'slug' => 'pro-test',
        'price_tzs' => 14_900_000, // TZS 149,000
        'billing_period' => 'monthly',
    ]);

    expect($plan->formatted_price)->toBe('TZS 149,000');
});

it('seeded three public plans from PlanSeeder', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::where('is_public', true)->count())->toBe(3);
    expect(Plan::pluck('slug')->all())->toContain('starter', 'pro', 'enterprise');
});
