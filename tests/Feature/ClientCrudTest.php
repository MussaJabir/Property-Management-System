<?php

use App\Models\Client;
use App\Models\Plan;
use App\Models\SuperAdminUser;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->admin = SuperAdminUser::create([
        'name' => 'Test Admin',
        'email' => 'crud-test@pms.local',
        'password' => 'password',
    ]);

    $this->plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan',
        'price_tzs' => 1_000_000,
        'billing_period' => 'monthly',
    ]);
});

it('creates a client with custom columns persisted', function () {
    $client = Client::create([
        'slug' => 'demo',
        'name' => 'Demo Properties Ltd',
        'contact_email' => 'hello@demo.example',
        'plan_id' => $this->plan->id,
        'status' => 'trial',
    ]);

    expect($client->fresh())
        ->id->toBe('demo')
        ->slug->toBe('demo')
        ->name->toBe('Demo Properties Ltd')
        ->status->toBe('trial')
        ->plan_id->toBe($this->plan->id);
});

it('exposes the plan relationship from a client', function () {
    $client = Client::create([
        'slug' => 'kariakoo',
        'name' => 'Kariakoo Heights',
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    expect($client->fresh()->plan->name)->toBe('Test Plan');
});

it('suspends and reactivates a client', function () {
    $client = Client::create([
        'slug' => 'demo3',
        'name' => 'Demo 3',
        'status' => 'active',
    ]);

    $client->update(['status' => 'suspended']);
    expect($client->fresh()->isSuspended())->toBeTrue();

    $client->update(['status' => 'active']);
    expect($client->fresh()->isActive())->toBeTrue();
});

it('enforces unique slugs across clients', function () {
    Client::create([
        'slug' => 'unique-slug',
        'name' => 'A',
        'status' => 'trial',
    ]);

    expect(fn () => Client::create([
        'slug' => 'unique-slug',
        'name' => 'B',
        'status' => 'trial',
    ]))->toThrow(QueryException::class);
});

it('soft-deletes clients', function () {
    $client = Client::create([
        'slug' => 'soft',
        'name' => 'Soft Delete',
        'status' => 'cancelled',
    ]);

    $client->delete();

    expect(Client::find('soft'))->toBeNull();
    expect(Client::withTrashed()->find('soft'))->not->toBeNull();
});
