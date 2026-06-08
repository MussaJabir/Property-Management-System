<?php

use App\Models\Client;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Regression guard for the cross-client data leak (PR #56).
 *
 * The bug: the operator panel never ran InitializeTenancyByUser, so the
 * TenantScopedModel global scope was inactive and an operator saw EVERY
 * client's data. The existing model-level scope test (InventoryTest) does
 * NOT catch this because it calls Tenancy::initialize() by hand — the bug
 * was specifically that the *panel HTTP request* failed to initialize
 * tenancy. So these tests must drive a real request through /manage.
 */
beforeEach(function () {
    // Client A — owns a property.
    $this->clientA = Client::create(['slug' => 'alpha', 'name' => 'Alpha Co', 'status' => 'active']);
    // Client B — brand new, owns nothing.
    $this->clientB = Client::create(['slug' => 'bravo', 'name' => 'Bravo Co', 'status' => 'active']);

    // Operator for each client (ClientObserver seeds roles per tenant_id).
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->clientA->id);
    $this->operatorA = User::create([
        'tenant_id' => $this->clientA->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Alpha Owner',
        'email' => 'owner@alpha.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->operatorA->assignRole('owner');

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->clientB->id);
    $this->operatorB = User::create([
        'tenant_id' => $this->clientB->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Bravo Owner',
        'email' => 'owner@bravo.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->operatorB->assignRole('owner');

    // Assignable technicians (never sign in) — distinct from the owners above so
    // assertions about the "Assigned to" dropdown can't be satisfied by the
    // logged-in owner's name appearing in the panel topbar.
    User::create([
        'tenant_id' => $this->clientA->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Alpha Technician',
        'email' => 'tech@alpha.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    User::create([
        'tenant_id' => $this->clientB->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Bravo Technician',
        'email' => 'tech@bravo.local',
        'password' => 'password',
        'status' => 'active',
    ]);

    // Seed a property for Client A only.
    Tenancy::initialize($this->clientA);
    $location = Location::create(['name' => 'Alpha Area', 'region' => 'Dar es Salaam', 'district' => 'Ilala']);
    Property::create([
        'location_id' => $location->id,
        'name' => 'Alpha Secret Tower',
        'type' => 'residential',
        'status' => 'active',
    ]);
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it("does not leak another client's properties to an operator", function () {
    // Client B's operator must NOT see Client A's property.
    $this->actingAs($this->operatorB, 'web')
        ->get('/manage/properties')
        ->assertOk()
        ->assertDontSee('Alpha Secret Tower');
});

it("shows an operator their own client's properties", function () {
    // Client A's operator MUST see Client A's property.
    $this->actingAs($this->operatorA, 'web')
        ->get('/manage/properties')
        ->assertOk()
        ->assertSee('Alpha Secret Tower');
});

it("does not leak another client's locations to an operator", function () {
    // Locations leaked too in the original report — guard them at the HTTP
    // level the same way (drives the real panel middleware stack).
    $this->actingAs($this->operatorB, 'web')
        ->get('/manage/locations')
        ->assertOk()
        ->assertDontSee('Alpha Area');
});

it("does not leak another client's operators in the maintenance assignment list", function () {
    // The "Assigned to" select had no tenant_id filter (User has no global
    // tenant scope), so it offered every client's staff. Drive the real create
    // page (preloaded options) and confirm Client B can't see Client A's tech.
    $this->actingAs($this->operatorB, 'web')
        ->get('/manage/maintenance-requests/create')
        ->assertOk()
        ->assertSee('Bravo Technician')
        ->assertDontSee('Alpha Technician');
});

it("shows an operator their own client's staff in the maintenance assignment list", function () {
    $this->actingAs($this->operatorA, 'web')
        ->get('/manage/maintenance-requests/create')
        ->assertOk()
        ->assertSee('Alpha Technician')
        ->assertDontSee('Bravo Technician');
});
