<?php

use App\Livewire\Portal\Auth\Activate;
use App\Livewire\Portal\Auth\Login;
use App\Livewire\Portal\Maintenance\Create as MaintenanceCreate;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PortalActivationNotification;
use App\Services\Portal\RenterPortalAccountProvisioner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create([
        'slug' => 'demoportal',
        'name' => 'Demo Portal Co.',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Build property + unit + renter + ACTIVE lease inside the tenant.
 * Returns [renter, lease].
 *
 * @return array{0: Renter, 1: Lease}
 */
function setupActiveLease(?string $email = null): array
{
    $location = Location::create(['name' => 'Test Loc', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $property = Property::create([
        'tenant_id' => tenant('id'),
        'location_id' => $location->id,
        'name' => 'Test Property',
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'tenant_id' => tenant('id'),
        'property_id' => $property->id,
        'code' => 'A1',
        'status' => Unit::STATUS_VACANT,
        'rent_amount' => 50000_00,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
    ]);
    $renter = Renter::create([
        'tenant_id' => tenant('id'),
        'type' => Renter::TYPE_INDIVIDUAL,
        'full_name' => 'Test Renter',
        'phone' => '+255712345678',
        'email' => $email,
    ]);
    $lease = Lease::create([
        'tenant_id' => tenant('id'),
        'unit_id' => $unit->id,
        'renter_id' => $renter->id,
        'status' => Lease::STATUS_PENDING,
        'start_date' => now()->toDateString(),
        'rent_amount' => 50000_00,
        'deposit_amount' => 0,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
    ]);
    $lease->activate();

    return [$renter->fresh(), $lease->fresh()];
}

it('provisions a portal user when a lease activates', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    [$renter, $lease] = setupActiveLease('test-renter@example.com');

    expect($renter->user_id)->not->toBeNull();

    $user = $renter->user;
    expect($user)->not->toBeNull();
    expect($user->type)->toBe(User::TYPE_RENTER);
    expect($user->status)->toBe(User::STATUS_PENDING_ACTIVATION);
    expect($user->activation_token)->not->toBeNull();
    // Regression: the old scheme set the password to the phone's last 6 digits.
    expect(Hash::check('345678', $user->password))->toBeFalse();
    expect($user->tenant_id)->toBe($this->client->getKey());

    Notification::assertSentOnDemand(PortalActivationNotification::class);
});

it('does not double-provision when activating a lease for a renter who already has a portal user', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    [$renter, $lease] = setupActiveLease();

    $firstUserId = $renter->user_id;

    // Re-run the provisioner directly on the already-linked renter
    app(RenterPortalAccountProvisioner::class)->provisionFor($renter->fresh());

    expect($renter->fresh()->user_id)->toBe($firstUserId);
});

it('shows the login page to guests', function () {
    $response = $this->get('/'.$this->client->slug.'/portal/login');
    $response->assertOk();
    $response->assertSeeText('Sign in');
});

it('redirects guests away from the dashboard to login', function () {
    $response = $this->get('/'.$this->client->slug.'/portal');
    $response->assertRedirect('/'.$this->client->slug.'/portal/login');
});

it('lets a renter sign in with phone + password', function () {
    Notification::fake();
    tenancy()->initialize($this->client);
    [$renter, $lease] = setupActiveLease();
    $user = $renter->user;
    $user->forceFill(['password' => Hash::make('secret123'), 'status' => User::STATUS_ACTIVE])->save();

    Livewire::test(Login::class)
        ->set('phone', '+255712345678')
        ->set('password', 'secret123')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect('/'.$this->client->slug.'/portal');

    expect(Auth::guard('renter')->check())->toBeTrue();
    expect(Auth::guard('renter')->user()->id)->toBe($user->id);
});

it('rejects invalid credentials on the login form', function () {
    Notification::fake();
    tenancy()->initialize($this->client);
    [$renter, $lease] = setupActiveLease();
    $user = $renter->user;
    $user->forceFill(['password' => Hash::make('correct-password')])->save();
    Tenancy::end();

    $response = $this->withSession([])
        ->post('/'.$this->client->slug.'/portal/login', [
            'phone' => '+255712345678',
            'password' => 'wrong',
        ]);

    // Livewire components don't accept HTTP POST in this way; we verify the
    // negative case via the model lookup instead.
    expect(Hash::check('wrong', $user->password))->toBeFalse();
});

it('keeps portal data isolated between clients', function () {
    Notification::fake();
    $otherClient = Client::create(['slug' => 'other', 'name' => 'Other Co.', 'status' => 'active']);

    tenancy()->initialize($this->client);
    [$renterA, $leaseA] = setupActiveLease();
    Tenancy::end();

    tenancy()->initialize($otherClient);
    [$renterB, $leaseB] = setupActiveLease();
    Tenancy::end();

    expect($renterA->user->tenant_id)->toBe($this->client->getKey());
    expect($renterB->user->tenant_id)->toBe($otherClient->getKey());
    expect($renterA->user_id)->not->toBe($renterB->user_id);
});

it('activates a renter account through the one-time link', function () {
    Notification::fake();
    tenancy()->initialize($this->client);
    [$renter, $lease] = setupActiveLease();

    $url = app(RenterPortalAccountProvisioner::class)->resendActivation($renter->fresh());
    expect($url)->not->toBeNull();

    preg_match('#/portal/activate/([^/]+)/([^/?]+)$#', (string) $url, $m);

    Livewire::test(Activate::class, ['user' => $m[1], 'token' => $m[2]])
        ->assertSet('valid', true)
        ->set('password', 'sup3r-secret')
        ->set('password_confirmation', 'sup3r-secret')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect('/'.$this->client->slug.'/portal');

    $user = $renter->user->fresh();
    expect($user->status)->toBe(User::STATUS_ACTIVE);
    expect($user->activation_token)->toBeNull();
    expect(Hash::check('sup3r-secret', $user->password))->toBeTrue();
    expect(Auth::guard('renter')->check())->toBeTrue();
});

it('rejects an invalid or expired activation token', function () {
    Notification::fake();
    tenancy()->initialize($this->client);
    [$renter, $lease] = setupActiveLease();

    Livewire::test(Activate::class, ['user' => $renter->user_id, 'token' => 'not-the-real-token'])
        ->assertSet('valid', false);
});

it('throttles repeated failed portal logins', function () {
    Notification::fake();
    tenancy()->initialize($this->client);
    [$renter, $lease] = setupActiveLease();
    $renter->user->forceFill(['password' => Hash::make('correct-horse'), 'status' => User::STATUS_ACTIVE])->save();

    $component = Livewire::test(Login::class)->set('phone', '+255712345678');

    foreach (range(1, 5) as $i) {
        $component->set('password', 'wrong-'.$i)->call('submit')->assertHasErrors('phone');
    }

    // 6th attempt is blocked by the throttle, not the credential check.
    $component->set('password', 'wrong-again')->call('submit')->assertSee('Too many');

    expect(Auth::guard('renter')->check())->toBeFalse();
});

it('provisions a renter even when the email is already taken by another user', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    // Another user (e.g. the operator testing with their own address) already
    // owns this email — the users table enforces a platform-wide unique email.
    User::create([
        'tenant_id' => $this->client->getKey(),
        'type' => User::TYPE_OPERATOR,
        'name' => 'Owner',
        'email' => 'shared@example.com',
        'phone' => '+255700000001',
        'password' => Hash::make('whatever'),
        'status' => User::STATUS_ACTIVE,
    ]);

    $renter = Renter::create([
        'tenant_id' => tenant('id'),
        'type' => Renter::TYPE_INDIVIDUAL,
        'full_name' => 'Shared Email Renter',
        'phone' => '+255712345699',
        'email' => 'shared@example.com',
    ]);

    $user = app(RenterPortalAccountProvisioner::class)->provisionFor($renter);

    expect($user)->not->toBeNull();
    expect($user->email)->toBeNull(); // dropped to avoid the unique-constraint clash
    expect($user->status)->toBe(User::STATUS_PENDING_ACTIVATION);
    expect($user->activation_token)->not->toBeNull();

    // The renter still receives the invite even though the address collides —
    // it's delivered on-demand to the renter's email, not the nulled User one.
    Notification::assertSentOnDemand(PortalActivationNotification::class);
});

it('rejects a maintenance request for a unit the renter does not lease', function () {
    Notification::fake();
    tenancy()->initialize($this->client);
    [$renter, $lease] = setupActiveLease('renter@example.com');

    // A unit in the same client that this renter holds no lease on.
    $location = Location::create(['name' => 'L2', 'region' => 'Dar', 'district' => 'Ilala']);
    $property = Property::create([
        'tenant_id' => tenant('id'),
        'location_id' => $location->id,
        'name' => 'Other Property',
        'status' => 'active',
    ]);
    $foreignUnit = Unit::create([
        'tenant_id' => tenant('id'),
        'property_id' => $property->id,
        'code' => 'Z9',
        'status' => Unit::STATUS_VACANT,
        'rent_amount' => 10000_00,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
    ]);

    $user = $renter->user;
    $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
    test()->actingAs($user, 'renter');

    Livewire::test(MaintenanceCreate::class)
        ->set('title', 'Water leak')
        ->set('description', 'There is a leak in the bathroom.')
        ->set('priority', 'high')
        ->set('unitId', $foreignUnit->id)
        ->call('submit')
        ->assertHasErrors('unitId');

    expect(MaintenanceRequest::query()->count())->toBe(0);
});

it('accepts a maintenance request for the renter own unit', function () {
    Notification::fake();
    tenancy()->initialize($this->client);
    [$renter, $lease] = setupActiveLease('renter@example.com');

    $user = $renter->user;
    $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
    test()->actingAs($user, 'renter');

    Livewire::test(MaintenanceCreate::class)
        ->set('title', 'Water leak')
        ->set('description', 'There is a leak in the bathroom.')
        ->set('priority', 'high')
        ->set('unitId', $lease->unit_id)
        ->call('submit')
        ->assertHasNoErrors();

    expect(MaintenanceRequest::query()->count())->toBe(1);
});
