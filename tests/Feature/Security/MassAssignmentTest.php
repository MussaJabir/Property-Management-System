<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Renter;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('ignores a mass-assigned tenant_id and uses the active tenant', function () {
    $clientA = Client::create(['slug' => 'mass-a', 'name' => 'A Co.', 'status' => 'active']);
    $clientB = Client::create(['slug' => 'mass-b', 'name' => 'B Co.', 'status' => 'active']);

    tenancy()->initialize($clientA);

    // Attacker-controlled payload trying to plant the row in another client.
    $renter = Renter::create([
        'tenant_id' => $clientB->getKey(),
        'type' => Renter::TYPE_INDIVIDUAL,
        'full_name' => 'Mass Assign Test',
        'phone' => '+255712000111',
    ]);

    expect($renter->tenant_id)->toBe($clientA->getKey())
        ->and($renter->tenant_id)->not->toBe($clientB->getKey());
});

it('does not mass-assign protected columns excluded from $fillable', function () {
    $client = Client::create(['slug' => 'mass-c', 'name' => 'C Co.', 'status' => 'active']);
    tenancy()->initialize($client);

    // user_id is set only by portal provisioning — never via mass assignment.
    $renter = Renter::create([
        'user_id' => 999,
        'type' => Renter::TYPE_INDIVIDUAL,
        'full_name' => 'No User Link',
        'phone' => '+255712000222',
    ]);

    expect($renter->user_id)->toBeNull();
});
