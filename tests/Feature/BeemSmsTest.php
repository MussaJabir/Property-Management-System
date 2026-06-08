<?php

declare(strict_types=1);

use App\Models\Client;
use App\Services\Admin\OperatorProvisioner;
use App\Services\Sms\BeemSmsSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'smsco', 'name' => 'SMS Co.', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('sends the activation link by SMS via Beem when configured', function () {
    config([
        'services.beem.api_key' => 'key',
        'services.beem.secret' => 'secret',
        'services.beem.source_addr' => 'PMS',
        'services.beem.endpoint' => 'https://apisms.beem.africa/v1/send',
    ]);
    Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true], 200)]);
    Notification::fake();

    app(OperatorProvisioner::class)->provision($this->client, 'Op', 'op@smsco.test', 'manager', '+255712000111');

    // Assert on the decoded payload — asJson() escapes forward slashes in the
    // raw body, so a string match on the URL would miss.
    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'beem.africa')
            && str_contains((string) ($request['message'] ?? ''), '/staff/activate/')
            && ($request['recipients'][0]['dest_addr'] ?? null) === '255712000111';
    });
});

it('does not call Beem when SMS is not configured', function () {
    config(['services.beem.api_key' => null, 'services.beem.secret' => null]);
    Http::fake();
    Notification::fake();

    app(OperatorProvisioner::class)->provision($this->client, 'Op', 'op2@smsco.test', 'manager', '+255712000222');

    Http::assertNothingSent();
});

it('the Beem sender reports failure gracefully when unconfigured', function () {
    config(['services.beem.api_key' => null, 'services.beem.secret' => null]);

    expect(app(BeemSmsSender::class)->send('+255712000333', 'hello'))->toBeFalse();
});
