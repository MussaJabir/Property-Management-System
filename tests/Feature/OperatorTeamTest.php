<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\RolePermissions;
use App\Filament\Operator\Resources\Operators\Pages\CreateOperator;
use App\Models\Client;
use App\Models\User;
use App\Notifications\OperatorActivationNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'teamco', 'name' => 'Team Co.', 'status' => 'active']);
    Filament::setCurrentPanel(Filament::getPanel('operator'));
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** Active operator on $this->client with the given role. */
function teamOperator(string $role): User
{
    /** @var Client $client */
    $client = test()->client;

    app(PermissionRegistrar::class)->setPermissionsTeamId($client->getKey());

    $user = User::create([
        'tenant_id' => $client->getKey(),
        'type' => User::TYPE_OPERATOR,
        'name' => ucfirst($role),
        'email' => $role.'@teamco.test',
        'password' => 'secret-password',
        'status' => User::STATUS_ACTIVE,
    ]);
    $user->assignRole($role);

    return $user;
}

it('lets the owner reach the Team page', function () {
    test()->actingAs(teamOperator('owner'), 'web');
    test()->get('/manage/operators')->assertOk();
});

it('lets a manager reach the Team page', function () {
    test()->actingAs(teamOperator('manager'), 'web');
    test()->get('/manage/operators')->assertOk();
});

it('blocks an accountant from the Team page', function () {
    test()->actingAs(teamOperator('accountant'), 'web');
    test()->get('/manage/operators')->assertForbidden();
});

it('lets the owner reach Roles & Permissions', function () {
    test()->actingAs(teamOperator('owner'), 'web');
    test()->get('/manage/role-permissions')->assertOk();
});

it('blocks a manager from Roles & Permissions', function () {
    test()->actingAs(teamOperator('manager'), 'web');
    test()->get('/manage/role-permissions')->assertForbidden();
});

it('invites a new operator with a role and emails an activation link', function () {
    Notification::fake();
    test()->actingAs(teamOperator('owner'), 'web');

    Livewire::test(CreateOperator::class)
        ->fillForm([
            'name' => 'New Accountant',
            'email' => 'newacct@teamco.test',
            'role' => 'accountant',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invited = User::query()->where('email', 'newacct@teamco.test')->first();
    expect($invited)->not->toBeNull();
    expect($invited->type)->toBe(User::TYPE_OPERATOR);
    expect($invited->status)->toBe(User::STATUS_PENDING_ACTIVATION);
    expect($invited->activation_token)->not->toBeNull();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->getKey());
    expect($invited->hasRole('accountant'))->toBeTrue();

    Notification::assertSentTo($invited, OperatorActivationNotification::class);
});

it('lets the owner customise a role permission set', function () {
    test()->actingAs(teamOperator('owner'), 'web');

    Livewire::test(RolePermissions::class)
        ->set('data.accountant', ['reports.view'])
        ->call('save')
        ->assertHasNoErrors();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->getKey());
    $role = Role::query()
        ->where('name', 'accountant')
        ->where('tenant_id', $this->client->getKey())
        ->first();

    expect($role->permissions->pluck('name')->all())->toBe(['reports.view']);
});
