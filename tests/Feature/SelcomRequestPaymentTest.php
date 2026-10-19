<?php

declare(strict_types=1);

use App\Exceptions\PaymentRequestRefused;
use App\Filament\Operator\Pages\FrontDesk;
use App\Filament\Operator\Resources\Invoices\Pages\ListInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\PaymentRequest;
use App\Models\Property;
use App\Models\Renter;
use App\Models\SelcomAccount;
use App\Models\Unit;
use App\Models\User;
use App\Services\FrontDesk\ShortStayService;
use App\Services\Payments\PaymentRequester;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    config(['services.selcom.sandbox_url' => 'https://selcom.test/api']);

    $this->client = Client::create(['slug' => 'mmco', 'name' => 'MM Rentals', 'status' => 'active', 'contact_email' => 'office@mm.test']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina',
        'email' => 'amina@mm.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Sinza', 'region' => 'Dar', 'district' => 'Ubungo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Block', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'B2', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $this->renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Hassan Omar', 'phone' => '0712345678']);
    $lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $this->renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => now()->subMonths(2)->toDateString(), 'rent_amount' => 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    $this->invoice = new Invoice([
        'lease_id' => $lease->id, 'invoice_number' => 'INV-2026-000777', 'currency' => 'TZS',
        'subtotal' => 300_000_00, 'total_amount' => 300_000_00, 'amount_paid' => 50_000_00,
        'billing_period_start' => now()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
    ]);
    $this->invoice->status = Invoice::STATUS_PARTIAL;
    $this->invoice->save();
    Tenancy::end();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function connectSelcom(Client $client, bool $enabled = true): void
{
    $account = new SelcomAccount([
        'mode' => 'sandbox', 'vendor' => 'TILL01', 'api_key' => 'k', 'api_secret' => 's',
        'enabled' => $enabled, 'verified_at' => now(),
    ]);
    $account->forceFill(['tenant_id' => $client->id])->save();
}

function fakeSelcom(string $orderCode = '000', string $pushCode = '111'): void
{
    Http::fake([
        'selcom.test/api/v1/checkout/create-order-minimal' => Http::response(['resultcode' => $orderCode, 'result' => $orderCode === '000' ? 'SUCCESS' : 'FAIL', 'message' => $orderCode === '000' ? 'Order created' : 'Duplicate order', 'reference' => 'R1', 'data' => []]),
        'selcom.test/api/v1/checkout/wallet-payment' => Http::response(['resultcode' => $pushCode, 'result' => $pushCode === '111' ? 'PENDING' : 'FAIL', 'message' => $pushCode === '111' ? 'Request in progress' : 'Invalid number', 'reference' => 'R2', 'data' => []]),
    ]);
}

it('creates a Selcom order for the balance in shillings and pushes the prompt', function () {
    connectSelcom($this->client);
    fakeSelcom();
    tenancy()->initialize($this->client);

    $request = app(PaymentRequester::class)->request($this->invoice, '0712 345 678', $this->owner->id);

    expect($request->status)->toBe(PaymentRequest::STATUS_PENDING)
        ->and($request->amount)->toBe(250_000_00)
        ->and($request->msisdn)->toBe('255712345678')
        ->and($request->order_id)->toStartWith('PMS')
        ->and($request->requested_by_user_id)->toBe($this->owner->id)
        ->and($request->tenant_id)->toBe('mmco');

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/create-order-minimal')
        && $r['vendor'] === 'TILL01'
        && $r['order_id'] === $request->order_id
        && $r['amount'] === 250_000
        && $r['currency'] === 'TZS'
        && $r['buyer_phone'] === '255712345678'
        && $r['buyer_name'] === 'Hassan Omar'
        && $r['buyer_email'] === 'office@mm.test'
        && $r['merchant_remarks'] === 'INV-2026-000777');

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet-payment')
        && $r->data() === ['transid' => $request->transid, 'order_id' => $request->order_id, 'msisdn' => '255712345678']);
});

it('marks the request failed and explains when Selcom refuses the order or the prompt', function (string $orderCode, string $pushCode, string $message) {
    connectSelcom($this->client);
    fakeSelcom($orderCode, $pushCode);
    tenancy()->initialize($this->client);

    expect(fn () => app(PaymentRequester::class)->request($this->invoice, '0712345678'))
        ->toThrow(PaymentRequestRefused::class, $message);

    expect(PaymentRequest::query()->sole()->status)->toBe(PaymentRequest::STATUS_FAILED);
})->with([
    'order refused' => ['403', '111', 'Duplicate order'],
    'prompt refused' => ['000', '403', 'Invalid number'],
]);

it('refuses bad numbers, paid invoices, quick repeats and workspaces without Selcom', function () {
    tenancy()->initialize($this->client);

    expect(fn () => app(PaymentRequester::class)->request($this->invoice, '0712345678'))
        ->toThrow(PaymentRequestRefused::class, 'not switched on');
    expect(PaymentRequester::canRequest($this->invoice))->toBeFalse();

    connectSelcom($this->client);
    fakeSelcom();

    expect(PaymentRequester::canRequest($this->invoice))->toBeTrue();
    expect(fn () => app(PaymentRequester::class)->request($this->invoice, '12345'))
        ->toThrow(PaymentRequestRefused::class, 'valid Tanzanian mobile number');

    app(PaymentRequester::class)->request($this->invoice, '0712345678');
    expect(fn () => app(PaymentRequester::class)->request($this->invoice, '0712345678'))
        ->toThrow(PaymentRequestRefused::class, 'just sent');

    $this->invoice->forceFill(['amount_paid' => 300_000_00, 'status' => Invoice::STATUS_PAID])->save();
    expect(PaymentRequester::canRequest($this->invoice))->toBeFalse();
    expect(fn () => app(PaymentRequester::class)->request($this->invoice, '0712345678'))
        ->toThrow(PaymentRequestRefused::class, 'nothing left to pay');
});

it('keeps the request pending when the prompt call times out', function () {
    connectSelcom($this->client);
    Http::fake([
        'selcom.test/api/v1/checkout/create-order-minimal' => Http::response(['resultcode' => '000', 'result' => 'SUCCESS', 'message' => 'ok', 'data' => []]),
        'selcom.test/api/v1/checkout/wallet-payment' => fn () => throw new ConnectionException('timed out'),
    ]);
    tenancy()->initialize($this->client);

    $request = app(PaymentRequester::class)->request($this->invoice, '0712345678');

    expect($request->fresh()->status)->toBe(PaymentRequest::STATUS_PENDING);
});

it('sends the prompt from an invoice row, prefilled with the renter phone', function () {
    connectSelcom($this->client);
    fakeSelcom();
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    Livewire::test(ListInvoices::class)
        ->assertActionVisible(TestAction::make('requestPayment')->table($this->invoice))
        ->mountAction(TestAction::make('requestPayment')->table($this->invoice))
        ->assertSchemaStateSet(['phone' => '+255712345678'], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Payment prompt sent');

    expect(PaymentRequest::query()->sole()->invoice_id)->toBe($this->invoice->id);
});

it('hides Request payment until Selcom is switched on', function () {
    connectSelcom($this->client, enabled: false);
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    Livewire::test(ListInvoices::class)
        ->assertActionHidden(TestAction::make('requestPayment')->table($this->invoice));
});

it('lets a renter send the prompt for their own invoice from the portal', function () {
    connectSelcom($this->client);
    fakeSelcom();

    $user = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_RENTER, 'name' => 'Hassan Omar',
        'email' => 'hassan@mm.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    tenancy()->initialize($this->client);
    $this->renter->forceFill(['user_id' => $user->id])->save();
    Tenancy::end();

    $this->actingAs($user, 'renter')
        ->get('/mmco/portal/invoices')
        ->assertOk()
        ->assertSee('Send payment prompt');

    $this->post('/mmco/portal/invoices/'.$this->invoice->id.'/pay', ['phone' => '0712345678'])
        ->assertRedirect('/mmco/portal/invoices')
        ->assertSessionHas('status');

    expect(PaymentRequest::query()->withoutGlobalScopes()->sole()->msisdn)->toBe('255712345678');
});

it('does not let a renter pay someone else\'s invoice', function () {
    connectSelcom($this->client);
    fakeSelcom();

    $user = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_RENTER, 'name' => 'Other',
        'email' => 'other@mm.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    tenancy()->initialize($this->client);
    Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Other', 'phone' => '0713000111', 'user_id' => $user->id]);
    Tenancy::end();

    $this->actingAs($user, 'renter')
        ->post('/mmco/portal/invoices/'.$this->invoice->id.'/pay', ['phone' => '0713000111'])
        ->assertNotFound();

    Http::assertNothingSent();
});

it('offers Request payment at the front desk for a guest who still owes', function () {
    connectSelcom($this->client);
    fakeSelcom();
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    $room = Unit::create([
        'property_id' => Property::query()->value('id'), 'code' => 'R1', 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
        'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 80_000_00,
    ]);
    $stay = app(ShortStayService::class)->book($room, ['full_name' => 'Asha Bakari', 'phone' => '0754111222'], today(), today()->addDays(2), checkInNow: true);

    Livewire::test(FrontDesk::class)
        ->mountAction(TestAction::make('stayRequestPayment')->arguments(['booking' => $stay->id]))
        ->assertSchemaStateSet(['phone' => '+255754111222'], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertNotified('Payment prompt sent');

    $request = PaymentRequest::query()->sole();
    expect($request->invoice_id)->toBe($stay->invoice->id)
        ->and($request->amount)->toBe(160_000_00);
});
