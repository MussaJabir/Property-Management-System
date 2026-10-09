<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Clients\Pages\CreateClient;
use App\Filament\Admin\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\SuperAdminUser;
use App\Models\User;
use App\Notifications\OperatorActivationNotification;
use App\Services\Admin\OperatorProvisioner;
use Filament\Facades\Filament;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'inviteco', 'name' => 'Invite Co.', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** Make every outgoing notification fail the way a rejected SMTP login does. */
function breakOutgoingMail(): void
{
    test()->mock(Dispatcher::class)
        ->shouldReceive('send')
        ->andThrow(new RuntimeException('535 Incorrect authentication data'));
}

function actAsSuperAdmin(): void
{
    test()->actingAs(SuperAdminUser::create([
        'name' => 'Invite Admin',
        'email' => 'invite-admin@pms.local',
        'password' => 'password',
    ]), 'super_admin');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
}

it('reports the invite as emailed when the send succeeds', function () {
    Notification::fake();

    $provisioner = app(OperatorProvisioner::class);
    $user = $provisioner->provision($this->client, 'Owner One', 'owner@inviteco.test', 'owner');

    expect($provisioner->lastInvite())->not->toBeNull()
        ->and($provisioner->lastInvite()->emailSent)->toBeTrue()
        ->and($provisioner->lastInvite()->url)->toContain('/staff/activate/'.$user->id.'/');
});

it('reports the invite as not emailed when the send fails, and still issues the link', function () {
    breakOutgoingMail();

    $provisioner = app(OperatorProvisioner::class);
    $user = $provisioner->provision($this->client, 'Owner One', 'owner@inviteco.test', 'owner');

    expect($user)->not->toBeNull()
        ->and($provisioner->lastInvite()->emailSent)->toBeFalse()
        ->and($provisioner->lastInvite()->url)->toContain('/staff/activate/'.$user->id.'/')
        ->and($user->fresh()->activation_token)->not->toBeNull();
});

it('issues no invite when the operator already exists', function () {
    Notification::fake();

    app(OperatorProvisioner::class)->provision($this->client, 'Owner One', 'owner@inviteco.test', 'owner');

    $provisioner = app(OperatorProvisioner::class);
    $provisioner->provision($this->client, 'Owner One', 'owner@inviteco.test', 'owner');

    expect($provisioner->lastInvite())->toBeNull();
});

it('lets a super admin resend the owner invite from the clients table', function () {
    Notification::fake();

    $owner = app(OperatorProvisioner::class)->provision($this->client, 'Owner One', 'owner@inviteco.test', 'owner');
    $firstToken = $owner->fresh()->activation_token;

    actAsSuperAdmin();

    Livewire::test(ListClients::class)
        ->assertTableActionVisible('resendOwnerInvite', $this->client)
        ->callTableAction('resendOwnerInvite', $this->client)
        ->assertNotified('Activation invite emailed');

    expect($owner->fresh()->activation_token)->not->toBe($firstToken);
    Notification::assertSentToTimes($owner, OperatorActivationNotification::class, 2);
});

it('warns the super admin and shows the link when the resent invite cannot be emailed', function () {
    Notification::fake();
    $owner = app(OperatorProvisioner::class)->provision($this->client, 'Owner One', 'owner@inviteco.test', 'owner');

    actAsSuperAdmin();
    breakOutgoingMail();

    Livewire::test(ListClients::class)
        ->callTableAction('resendOwnerInvite', $this->client)
        ->assertNotified('Invite email could not be sent');

    expect($owner->fresh()->status)->toBe(User::STATUS_PENDING_ACTIVATION);
});

it('hides the resend action once the owner has activated', function () {
    Notification::fake();

    $owner = app(OperatorProvisioner::class)->provision($this->client, 'Owner One', 'owner@inviteco.test', 'owner');
    $owner->forceFill(['status' => User::STATUS_ACTIVE])->save();

    actAsSuperAdmin();

    Livewire::test(ListClients::class)
        ->assertTableActionHidden('resendOwnerInvite', $this->client);
});

it('does not offer the resend action for pending staff who are not the owner', function () {
    Notification::fake();

    app(OperatorProvisioner::class)->provision($this->client, 'Staff One', 'staff@inviteco.test', 'manager');

    actAsSuperAdmin();

    Livewire::test(ListClients::class)
        ->assertTableActionHidden('resendOwnerInvite', $this->client);
});

it('warns instead of claiming success when a new client owner cannot be emailed', function () {
    actAsSuperAdmin();
    breakOutgoingMail();

    Livewire::test(CreateClient::class)
        ->fillForm([
            'name' => 'Fresh Lettings',
            'slug' => 'fresh-lettings',
            'status' => 'trial',
            'owner_name' => 'Fresh Owner',
            'owner_email' => 'Fresh.Owner@Outlook.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Invite email could not be sent');

    $owner = User::query()->where('email', 'fresh.owner@outlook.com')->first();

    expect($owner)->not->toBeNull()
        ->and($owner->status)->toBe(User::STATUS_PENDING_ACTIVATION);
});

it('confirms the invite was emailed when a new client owner is created', function () {
    Notification::fake();
    actAsSuperAdmin();

    Livewire::test(CreateClient::class)
        ->fillForm([
            'name' => 'Fresh Lettings',
            'slug' => 'fresh-lettings',
            'status' => 'trial',
            'owner_name' => 'Fresh Owner',
            'owner_email' => 'fresh.owner@outlook.com',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Activation invite emailed');
});
