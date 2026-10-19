<?php

use App\Exceptions\SelcomUnavailable;
use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Models\Client;
use App\Models\PaymentRequest;
use App\Models\SelcomAccount;
use App\Models\User;
use App\Services\Payments\Selcom\SelcomClient;
use App\Services\Payments\Selcom\SelcomResponse;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

const SELCOM_TEST_URL = 'https://selcom.test/api';

beforeEach(function () {
    config(['services.selcom.sandbox_url' => SELCOM_TEST_URL, 'services.selcom.live_url' => null]);

    $this->client = Client::create(['slug' => 'payco', 'name' => 'Pay Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);

    $this->owner = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Owner',
        'email' => 'owner@payco.local',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->owner->assignRole('owner');

    Filament::setCurrentPanel(Filament::getPanel('operator'));
});

function selcomClient(): SelcomClient
{
    return new SelcomClient(SELCOM_TEST_URL, 'the-key', 'the-secret', 'VENDOR1');
}

function selcomOk(array $data = []): array
{
    return ['reference' => 'R1', 'resultcode' => '000', 'result' => 'SUCCESS', 'message' => 'ok', 'data' => $data];
}

it('signs requests the way Selcom documents', function () {
    $at = Carbon::parse('2019-02-26T09:30:46+03:00');

    $headers = selcomClient()->headers(['order_id' => '85gh9p7l8', 'amount' => 8000], $at);

    $expected = base64_encode(hash_hmac('sha256', 'timestamp=2019-02-26T09:30:46+03:00&order_id=85gh9p7l8&amount=8000', 'the-secret', true));

    expect($headers)->toBe([
        'Authorization' => 'SELCOM '.base64_encode('the-key'),
        'Timestamp' => '2019-02-26T09:30:46+03:00',
        'Digest-Method' => 'HS256',
        'Digest' => $expected,
        'Signed-Fields' => 'order_id,amount',
    ]);
});

it('creates a minimal order with the vendor first and base64 URLs', function () {
    Http::fake(['selcom.test/*' => Http::response(selcomOk([['payment_token' => '8000']]))]);

    $response = selcomClient()->createOrderMinimal([
        'order_id' => 'PMS1',
        'buyer_email' => 'a@b.test',
        'buyer_name' => 'Asha',
        'buyer_phone' => '255712000111',
        'amount' => 5000,
        'currency' => 'TZS',
        'webhook' => 'https://pms.test/hook',
        'no_of_items' => 1,
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->first()['payment_token'])->toBe('8000');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === SELCOM_TEST_URL.'/v1/checkout/create-order-minimal'
            && array_key_first($request->data()) === 'vendor'
            && $request['vendor'] === 'VENDOR1'
            && $request['webhook'] === base64_encode('https://pms.test/hook')
            && $request->header('Signed-Fields')[0] === implode(',', array_keys($request->data()));
    });
});

it('sends order-status and cancel-order fields in the query string', function () {
    Http::fake(['selcom.test/*' => Http::response(selcomOk([['payment_status' => 'PENDING']]))]);

    selcomClient()->orderStatus('PMS1');
    selcomClient()->cancelOrder('PMS1');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
        && $r->url() === SELCOM_TEST_URL.'/v1/checkout/order-status?order_id=PMS1'
        && $r->header('Signed-Fields')[0] === 'order_id');
    Http::assertSent(fn (Request $r): bool => $r->method() === 'DELETE'
        && $r->url() === SELCOM_TEST_URL.'/v1/checkout/cancel-order?order_id=PMS1'
        && $r->body() === '');
});

it('reads Selcom result codes', function (string $code, string $state) {
    $response = SelcomResponse::fromArray(['resultcode' => $code, 'result' => 'X', 'message' => '', 'data' => []]);

    expect(match (true) {
        $response->successful() => 'success',
        $response->inProgress() => 'in progress',
        $response->ambiguous() => 'ambiguous',
        $response->failed() => 'failed',
    })->toBe($state);
})->with([
    ['000', 'success'],
    ['111', 'in progress'],
    ['927', 'in progress'],
    ['999', 'ambiguous'],
    ['403', 'failed'],
]);

it('reports an unusable reply or a network failure as unavailable', function () {
    Http::fake(['selcom.test/*' => Http::response('<html>Bad gateway</html>', 502)]);

    expect(fn () => selcomClient()->orderStatus('PMS1'))->toThrow(SelcomUnavailable::class);

    Http::fake(['selcom.test/*' => fn () => throw new ConnectionException('timed out')]);

    expect(fn () => selcomClient()->orderStatus('PMS1'))->toThrow(SelcomUnavailable::class);
});

it('refuses to build a client without keys or a Selcom address', function () {
    $account = new SelcomAccount(['mode' => 'live', 'vendor' => 'V', 'api_key' => 'k', 'api_secret' => 's']);

    expect(fn () => SelcomClient::for($account))->toThrow(SelcomUnavailable::class);
    expect(fn () => SelcomClient::for(new SelcomAccount(['mode' => 'sandbox'])))->toThrow(SelcomUnavailable::class);
});

it('saves Selcom keys encrypted and keeps them when left blank', function () {
    $this->actingAs($this->owner, 'web');

    Livewire::test(WorkspaceSettings::class)
        ->set('data.selcom_vendor', 'VENDOR1')
        ->set('data.selcom_api_key', 'the-key')
        ->set('data.selcom_api_secret', 'the-secret')
        ->call('save')
        ->assertHasNoErrors();

    $account = SelcomAccount::forClient($this->client);
    expect($account->api_key)->toBe('the-key')
        ->and($account->api_secret)->toBe('the-secret')
        ->and($account->mode)->toBe('sandbox')
        ->and($account->enabled)->toBeFalse();

    $raw = DB::table('selcom_accounts')->where('tenant_id', 'payco')->first();
    expect($raw->api_key)->not->toContain('the-key')
        ->and($raw->api_secret)->not->toContain('the-secret');

    Livewire::test(WorkspaceSettings::class)
        ->assertSet('data.selcom_api_key', null)
        ->set('data.selcom_vendor', 'VENDOR2')
        ->call('save');

    expect(SelcomAccount::forClient($this->client)->api_key)->toBe('the-key');
});

it('only turns payments on after Test connection passes', function () {
    $this->actingAs($this->owner, 'web');
    Http::fake(['selcom.test/*' => Http::response(selcomOk())]);

    Livewire::test(WorkspaceSettings::class)
        ->set('data.selcom_vendor', 'VENDOR1')
        ->set('data.selcom_api_key', 'the-key')
        ->set('data.selcom_api_secret', 'the-secret')
        ->set('data.selcom_enabled', true)
        ->call('save')
        ->assertSet('data.selcom_enabled', false);

    expect(SelcomAccount::forClient($this->client)->enabled)->toBeFalse();

    Livewire::test(WorkspaceSettings::class)
        ->call('testSelcom')
        ->set('data.selcom_enabled', true)
        ->call('save');

    $account = SelcomAccount::forClient($this->client);
    expect($account->verified_at)->not->toBeNull()
        ->and($account->enabled)->toBeTrue()
        ->and($account->isReady())->toBeTrue();

    Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), SELCOM_TEST_URL.'/v1/checkout/list-orders?fromdate='));

    // A new key needs a new test, and payments go off until then.
    Livewire::test(WorkspaceSettings::class)
        ->set('data.selcom_api_key', 'another-key')
        ->call('save');

    $account->refresh();
    expect($account->verified_at)->toBeNull()
        ->and($account->enabled)->toBeFalse();
});

it('does not mark the keys tested when Selcom refuses them', function () {
    $this->actingAs($this->owner, 'web');
    Http::fake(['selcom.test/*' => Http::response(['resultcode' => '403', 'result' => 'FAIL', 'message' => 'Invalid key', 'data' => []])]);

    Livewire::test(WorkspaceSettings::class)
        ->set('data.selcom_vendor', 'VENDOR1')
        ->set('data.selcom_api_key', 'bad')
        ->set('data.selcom_api_secret', 'bad')
        ->call('save')
        ->call('testSelcom')
        ->assertNotified('Selcom refused the keys');

    expect(SelcomAccount::forClient($this->client)->verified_at)->toBeNull();
});

it('gives payment requests unique Selcom references', function () {
    $a = PaymentRequest::newReference('PMS');
    $b = PaymentRequest::newReference('PMS');

    expect($a)->toMatch('/^PMS[A-Z0-9]{16}$/')->and($a)->not->toBe($b);
});
