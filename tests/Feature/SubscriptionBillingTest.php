<?php

use App\Filament\Admin\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SuperAdminUser;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = SuperAdminUser::create([
        'name' => 'Billing Admin',
        'email' => 'billing-admin@pms.local',
        'password' => 'password',
    ]);

    $this->client = Client::create(['slug' => 'billco', 'name' => 'Bill Co', 'status' => 'active']);
    $this->plan = Plan::create([
        'name' => 'Pro', 'slug' => 'pro-test', 'price_tzs' => 4_900_000,
        'billing_period' => 'monthly', 'is_public' => true,
    ]);

    $this->actingAs($this->admin, 'super_admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('resolves the client relation via tenant_id', function () {
    $sub = Subscription::create([
        'tenant_id' => $this->client->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'started_at' => now(),
    ]);

    expect($sub->client)->not->toBeNull()
        ->and($sub->client->name)->toBe('Bill Co');
});

it('maps billing periods to months', function () {
    $sub = new Subscription(['plan_id' => $this->plan->id]);
    $sub->setRelation('plan', new Plan(['billing_period' => 'monthly']));
    expect($sub->billingMonths())->toBe(1);

    $sub->setRelation('plan', new Plan(['billing_period' => 'quarterly']));
    expect($sub->billingMonths())->toBe(3);

    $sub->setRelation('plan', new Plan(['billing_period' => 'annual']));
    expect($sub->billingMonths())->toBe(12);
});

it('records a payment, logs it, and extends a past-due subscription', function () {
    $sub = Subscription::create([
        'tenant_id' => $this->client->id,
        'plan_id' => $this->plan->id,
        'status' => 'past_due',
        'started_at' => now()->subMonth(),
        'ends_at' => now()->subDay(), // overdue
    ]);

    Livewire::test(ListSubscriptions::class)
        ->callTableAction('recordPayment', $sub, data: [
            'amount' => 49000,
            'paid_at' => now()->toDateString(),
            'method' => 'bank_transfer',
            'reference' => 'TX-001',
        ])
        ->assertHasNoTableActionErrors();

    $sub->refresh();

    // A payment row was logged with the amount in cents.
    expect(SubscriptionPayment::where('subscription_id', $sub->id)->count())->toBe(1);
    expect((int) $sub->payments()->sum('amount_tzs'))->toBe(4_900_000);

    // The subscription was reactivated and its period extended into the future.
    expect($sub->status)->toBe('active')
        ->and($sub->ends_at->isFuture())->toBeTrue();
});
