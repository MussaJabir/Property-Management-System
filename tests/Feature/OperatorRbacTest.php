<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'rbacco', 'name' => 'RBAC Co.', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** Create an active operator on $this->client with the given role. */
function rbacOperator(string $role): User
{
    /** @var Client $client */
    $client = test()->client;

    app(PermissionRegistrar::class)->setPermissionsTeamId($client->getKey());

    $user = User::create([
        'tenant_id' => $client->getKey(),
        'type' => User::TYPE_OPERATOR,
        'name' => ucfirst($role),
        'email' => $role.'@rbacco.test',
        'password' => 'secret-password',
        'status' => User::STATUS_ACTIVE,
    ]);
    $user->assignRole($role);

    return $user;
}

it('gives the owner full access across the panel', function () {
    test()->actingAs(rbacOperator('owner'), 'web');

    test()->get('/manage/properties')->assertOk();
    test()->get('/manage/properties/create')->assertOk();
    test()->get('/manage/invoices/create')->assertOk();
    test()->get('/manage/cms-pages')->assertOk();
    test()->get('/manage/monthly-rent-collection-page')->assertOk();
});

it('lets a manager manage operations', function () {
    test()->actingAs(rbacOperator('manager'), 'web');

    test()->get('/manage/properties/create')->assertOk();
    test()->get('/manage/invoices/create')->assertOk();
    test()->get('/manage/cms-pages')->assertOk();
    test()->get('/manage/monthly-rent-collection-page')->assertOk();
});

it('limits an accountant to billing/expenses + read-only inventory & tenancy', function () {
    test()->actingAs(rbacOperator('accountant'), 'web');

    // Finance: full.
    test()->get('/manage/invoices')->assertOk();
    test()->get('/manage/invoices/create')->assertOk();
    test()->get('/manage/expenses/create')->assertOk();
    test()->get('/manage/monthly-rent-collection-page')->assertOk();

    // Inventory & tenancy: read-only.
    test()->get('/manage/properties')->assertOk();
    test()->get('/manage/properties/create')->assertForbidden();

    // Maintenance: view only; CMS: none.
    test()->get('/manage/maintenance-requests')->assertOk();
    test()->get('/manage/maintenance-requests/create')->assertForbidden();
    test()->get('/manage/cms-announcements')->assertForbidden();
});

it('limits maintenance-staff to maintenance + read-only inventory', function () {
    test()->actingAs(rbacOperator('maintenance-staff'), 'web');

    test()->get('/manage/maintenance-requests')->assertOk();
    test()->get('/manage/maintenance-requests/create')->assertOk();

    test()->get('/manage/properties')->assertOk();
    test()->get('/manage/properties/create')->assertForbidden();

    // No billing, no reports.
    test()->get('/manage/invoices')->assertForbidden();
    test()->get('/manage/monthly-rent-collection-page')->assertForbidden();
});
