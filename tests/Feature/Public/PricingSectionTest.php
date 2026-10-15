<?php

declare(strict_types=1);

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;

it('lists every public plan with its price, limits and features', function () {
    $this->seed(PlanSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertSee('id="pricing"', false)
        ->assertSeeInOrder(['Basic', '25,000', 'TZS 250,000 a year', 'Starter', '49,000', 'Pro', '149,000', 'Enterprise', 'On request'])
        ->assertSeeInOrder(['Starter', 'Properties', '3', 'Units', '60', 'Staff accounts', '6'])
        ->assertSee('Unlimited')
        ->assertSee('Custom staff roles');
});

it('marks features that are not built yet as coming soon', function () {
    Plan::create([
        'name' => 'Reminders',
        'slug' => 'reminders',
        'price_tzs' => 1_000_000,
        'billing_period' => 'monthly',
        'max_units' => 10,
        'features' => ['sms_notifications'],
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['SMS rent reminders', 'Coming soon']);
});

it('keeps private plans off the public page', function () {
    Plan::create(['name' => 'Visible Plan', 'slug' => 'visible', 'price_tzs' => 1_000_000, 'billing_period' => 'monthly', 'is_public' => true]);
    Plan::create(['name' => 'Secret Deal', 'slug' => 'secret', 'price_tzs' => 500_000, 'billing_period' => 'monthly', 'is_public' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Visible Plan')
        ->assertDontSee('Secret Deal');
});

it('hides the pricing section and its links when no plan is public', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('id="pricing"', false)
        ->assertDontSee('href="#pricing"', false);
});

it('shows the pricing section in Swahili', function () {
    $this->seed(PlanSeeder::class);

    $this->withSession(['locale' => 'sw'])
        ->get('/')
        ->assertOk()
        ->assertSee('Bila kikomo')
        ->assertSee('au TZS 250,000 kwa mwaka');
});

it('moves an existing install to the October 2026 plan limits', function () {
    DB::table('plans')->insert([
        ['name' => 'Starter', 'slug' => 'starter', 'price_tzs' => 4_900_000, 'billing_period' => 'monthly', 'max_properties' => 3, 'max_units' => 30, 'max_operators' => 2, 'features' => json_encode(['renter_portal']), 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Pro', 'slug' => 'pro', 'price_tzs' => 14_900_000, 'billing_period' => 'monthly', 'max_properties' => 15, 'max_units' => 150, 'max_operators' => 8, 'features' => json_encode(['reports']), 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration = require database_path('migrations/2026_10_15_114902_update_plans_for_october_2026_pricing.php');
    $migration->up();

    $starter = Plan::where('slug', 'starter')->firstOrFail();
    $pro = Plan::where('slug', 'pro')->firstOrFail();
    $basic = Plan::where('slug', 'basic')->firstOrFail();

    expect([$starter->max_properties, $starter->max_units, $starter->max_operators])->toBe([3, 60, 6])
        ->and([$pro->max_properties, $pro->max_units, $pro->max_operators])->toBe([15, 300, 30])
        ->and([$basic->price_tzs, $basic->max_properties, $basic->max_units, $basic->max_operators])->toBe([2_500_000, 1, 20, 2])
        ->and($pro->features)->toContain('custom_roles');

    $migration->down();

    expect(Plan::where('slug', 'starter')->value('max_units'))->toBe(30)
        ->and(Plan::where('slug', 'basic')->exists())->toBeFalse();
});

it('leaves a database without the seeded catalogue untouched', function () {
    $migration = require database_path('migrations/2026_10_15_114902_update_plans_for_october_2026_pricing.php');
    $migration->up();

    expect(Plan::count())->toBe(0);
});
