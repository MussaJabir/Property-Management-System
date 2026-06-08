<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Plans\Pages\CreatePlan;
use App\Models\Plan;
use App\Models\SuperAdminUser;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('stores and reloads features as a flat list of catalogue keys', function () {
    $plan = Plan::create([
        'name' => 'List Plan',
        'slug' => 'list-plan',
        'price_tzs' => 1_000_000,
        'billing_period' => 'monthly',
        'features' => ['renter_portal', 'reports'],
    ]);

    expect($plan->fresh()->features)->toBe(['renter_portal', 'reports']);
});

it('maps selected feature keys to labels in catalogue order and drops unknown keys', function () {
    $plan = Plan::create([
        'name' => 'Label Plan',
        'slug' => 'label-plan',
        'price_tzs' => 0,
        'billing_period' => 'monthly',
        // Out of order + one retired/unknown key — featureLabels() normalises.
        'features' => ['reports', 'renter_portal', 'legacy_feature'],
    ]);

    expect($plan->featureLabels())->toBe(['Renter portal', 'Reports & Excel export']);
});

it('returns no labels when a plan has no features', function () {
    $plan = Plan::create([
        'name' => 'Bare Plan',
        'slug' => 'bare-plan',
        'price_tzs' => 0,
        'billing_period' => 'monthly',
    ]);

    expect($plan->featureLabels())->toBe([]);
});

it('seeds plans using only valid catalogue keys', function () {
    $this->seed(PlanSeeder::class);

    $validKeys = array_keys(Plan::FEATURES);

    Plan::all()->each(function (Plan $plan) use ($validKeys) {
        expect($plan->features)->each->toBeIn($validKeys);
    });
});

it('persists ticked features from the admin create form as a flat list', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = SuperAdminUser::create([
        'name' => 'Plan Admin',
        'email' => 'plan-admin@pms.local',
        'password' => 'password',
    ]);
    $this->actingAs($admin, 'super_admin');

    Livewire::test(CreatePlan::class)
        ->fillForm([
            'name' => 'Form Plan',
            'slug' => 'form-plan',
            'price_tzs' => 4_900_000,
            'billing_period' => 'monthly',
            'features' => ['renter_portal', 'maintenance', 'reports'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = Plan::where('slug', 'form-plan')->first();

    expect($plan)->not->toBeNull();
    expect($plan->features)->toBe(['renter_portal', 'maintenance', 'reports']);
});
