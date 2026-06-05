<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\User;
use App\Services\Admin\OperatorProvisioner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'actco', 'name' => 'Act Co.', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Invite an operator and recover the raw activation token (via resend, which
 * returns the URL) so the test can drive the activation link.
 *
 * @return array{0: User, 1: string, 2: string}
 */
function inviteOperator(string $email = 'staff@actco.test'): array
{
    Notification::fake();

    /** @var Client $client */
    $client = test()->client;

    $user = app(OperatorProvisioner::class)->provision($client, 'New Staff', $email, 'manager');
    $url = app(OperatorProvisioner::class)->resend($user->fresh());

    preg_match('#/staff/activate/([^/]+)/([^/?]+)$#', $url, $m);

    return [$user->fresh(), $m[1], $m[2]];
}

it('shows the activation form for a valid link', function () {
    [$user, $id, $token] = inviteOperator();

    test()->get('/staff/activate/'.$id.'/'.$token)
        ->assertOk()
        ->assertSee('Activate account');
});

it('activates the operator, sets their password, and signs them in', function () {
    [$user, $id, $token] = inviteOperator();

    test()->post('/staff/activate/'.$id.'/'.$token, [
        'password' => 'sup3r-secret',
        'password_confirmation' => 'sup3r-secret',
    ])->assertRedirect('/manage');

    $fresh = $user->fresh();
    expect($fresh->status)->toBe(User::STATUS_ACTIVE);
    expect($fresh->activation_token)->toBeNull();
    expect(Hash::check('sup3r-secret', $fresh->password))->toBeTrue();

    test()->assertAuthenticated('web');
});

it('rejects an invalid or expired activation token', function () {
    [$user, $id, $token] = inviteOperator();

    test()->get('/staff/activate/'.$id.'/not-the-token')
        ->assertOk()
        ->assertSee('invalid or has expired');

    test()->post('/staff/activate/'.$id.'/not-the-token', [
        'password' => 'sup3r-secret',
        'password_confirmation' => 'sup3r-secret',
    ])->assertSessionHasErrors('password');

    expect($user->fresh()->status)->toBe(User::STATUS_PENDING_ACTIVATION);
});
