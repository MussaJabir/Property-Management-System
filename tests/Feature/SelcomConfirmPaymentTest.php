<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Invoices\Pages\ListInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Property;
use App\Models\Renter;
use App\Models\SelcomAccount;
use App\Models\Unit;
use App\Models\User;
use App\Services\Payments\PaymentConfirmer;
use App\Services\Payments\PaymentRequester;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

const CONFIRM_STATUS_URL = 'selcom.test/api/v1/checkout/order-status*';

beforeEach(function () {
    config(['services.selcom.sandbox_url' => 'https://selcom.test/api']);

    $this->client = Client::create(['slug' => 'cfco', 'name' => 'Confirm Co', 'status' => 'active']);
    $this->other = Client::create(['slug' => 'cfother', 'name' => 'Other Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina',
        'email' => 'amina@cf.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    $account = new SelcomAccount(['mode' => 'sandbox', 'vendor' => 'TILL01', 'api_key' => 'k', 'api_secret' => 's', 'enabled' => true, 'verified_at' => now()]);
    $account->forceFill(['tenant_id' => $this->client->id])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Mbezi', 'region' => 'Dar', 'district' => 'Ubungo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Block', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'C3', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 200_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Neema Mushi', 'phone' => '0713222333']);
    $lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => now()->subMonth()->toDateString(), 'rent_amount' => 200_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    $this->invoice = new Invoice([
        'lease_id' => $lease->id, 'invoice_number' => 'INV-2026-000888', 'currency' => 'TZS',
        'subtotal' => 200_000_00, 'total_amount' => 200_000_00,
        'billing_period_start' => now()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
    ]);
    $this->invoice->status = Invoice::STATUS_UNPAID;
    $this->invoice->save();

    $this->request = PaymentRequest::create([
        'invoice_id' => $this->invoice->id, 'amount' => 200_000_00, 'currency' => 'TZS',
        'msisdn' => '255713222333', 'status' => PaymentRequest::STATUS_PENDING,
    ]);
    Tenancy::end();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** Selcom's order-status reply for $orderId. */
function selcomOrder(string $orderId, string $status, array $extra = []): array
{
    return [
        'reference' => 'S1', 'resultcode' => '000', 'result' => 'SUCCESS', 'message' => 'Order fetch successful',
        'data' => [array_merge([
            'order_id' => $orderId, 'creation_date' => '2026-10-19 10:00:00', 'amount' => '200000', 'payment_status' => $status,
            'transid' => null, 'channel' => null, 'reference' => null, 'phone' => null,
        ], $extra)],
    ];
}

function selcomCallback(PaymentRequest $request): array
{
    return [
        'result' => 'SUCCESS', 'resultcode' => '000', 'order_id' => $request->order_id, 'transid' => $request->transid,
        'reference' => '856266164161', 'channel' => 'TIGOPESATZ', 'amount' => '200000', 'phone' => '255713222333',
        'payment_status' => 'COMPLETED',
    ];
}

it('records the payment and issues the receipt once Selcom confirms the order', function () {
    Http::fake([CONFIRM_STATUS_URL => Http::response(selcomOrder($this->request->order_id, 'COMPLETED', [
        'transid' => '7945454515', 'channel' => 'TIGOPESATZ', 'reference' => '856266164161', 'msisdn' => '255713222333',
    ]))]);

    $this->postJson('/cfco/payments/selcom/webhook', selcomCallback($this->request))
        ->assertOk()
        ->assertJson(['status' => PaymentConfirmer::OUTCOME_PAID]);

    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'order-status?order_id='.$this->request->order_id));

    tenancy()->initialize($this->client);
    $payment = Payment::query()->sole();
    expect($payment->amount)->toBe(200_000_00)
        ->and($payment->method)->toBe(Payment::METHOD_MOBILE_MONEY)
        ->and($payment->mobile_money_provider)->toBe(Payment::PROVIDER_TIGOPESA)
        ->and($payment->transaction_id)->toBe('7945454515')
        ->and($payment->reference_number)->toBe('856266164161')
        ->and($payment->received_by_user_id)->toBeNull()
        ->and($payment->receipt)->not->toBeNull()
        ->and($this->invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);

    $request = $this->request->fresh();
    expect($request->status)->toBe(PaymentRequest::STATUS_COMPLETED)
        ->and($request->payment_id)->toBe($payment->id)
        ->and($request->channel)->toBe('TIGOPESATZ')
        ->and($request->completed_at)->not->toBeNull();
});

it('records only once when Selcom calls back twice', function () {
    Http::fake([CONFIRM_STATUS_URL => Http::response(selcomOrder($this->request->order_id, 'COMPLETED', ['transid' => 'T1', 'channel' => 'AIRTELMONEY']))]);

    $this->postJson('/cfco/payments/selcom/webhook', selcomCallback($this->request))->assertJson(['status' => 'paid']);
    $this->postJson('/cfco/payments/selcom/webhook', selcomCallback($this->request))->assertJson(['status' => 'done']);

    tenancy()->initialize($this->client);
    expect(Payment::query()->count())->toBe(1);
});

it('does not trust the callback body: nothing is recorded while Selcom says pending', function () {
    Http::fake([CONFIRM_STATUS_URL => Http::response(selcomOrder($this->request->order_id, 'PENDING'))]);

    $this->postJson('/cfco/payments/selcom/webhook', selcomCallback($this->request))
        ->assertJson(['status' => PaymentConfirmer::OUTCOME_PENDING]);

    tenancy()->initialize($this->client);
    expect(Payment::query()->count())->toBe(0)
        ->and($this->request->fresh()->status)->toBe(PaymentRequest::STATUS_PENDING)
        ->and($this->request->fresh()->last_checked_at)->not->toBeNull();
});

it('closes the request when the payer cancels or the network rejects it', function (string $status) {
    Http::fake([CONFIRM_STATUS_URL => Http::response(selcomOrder($this->request->order_id, $status))]);
    tenancy()->initialize($this->client);

    expect(app(PaymentConfirmer::class)->confirm($this->request))->toBe(PaymentConfirmer::OUTCOME_CANCELLED)
        ->and($this->request->fresh()->status)->toBe(PaymentRequest::STATUS_CANCELLED)
        ->and(Payment::query()->count())->toBe(0);
})->with(['CANCELLED', 'USERCANCELLED', 'REJECTED']);

it('stays pending when Selcom cannot be reached or answers about another order', function () {
    tenancy()->initialize($this->client);

    Http::fake([CONFIRM_STATUS_URL => Http::response('down', 503)]);
    expect(app(PaymentConfirmer::class)->confirm($this->request))->toBe(PaymentConfirmer::OUTCOME_PENDING);

    Http::fake([CONFIRM_STATUS_URL => Http::response(selcomOrder('SOMEONEELSE', 'COMPLETED'))]);
    expect(app(PaymentConfirmer::class)->confirm($this->request))->toBe(PaymentConfirmer::OUTCOME_PENDING)
        ->and(Payment::query()->count())->toBe(0);
});

it('ignores callbacks for unknown orders and for another workspace\'s orders', function () {
    Http::fake();

    $this->postJson('/cfco/payments/selcom/webhook', ['order_id' => 'PMSNOTOURS'])->assertNotFound();
    $this->postJson('/cfother/payments/selcom/webhook', selcomCallback($this->request))->assertNotFound();

    Http::assertNothingSent();
});

it('sends the workspace callback address with each order, base64 encoded', function () {
    Http::fake([
        'selcom.test/api/v1/checkout/create-order-minimal' => Http::response(['resultcode' => '000', 'result' => 'SUCCESS', 'message' => 'ok', 'data' => []]),
        'selcom.test/api/v1/checkout/wallet-payment' => Http::response(['resultcode' => '111', 'result' => 'PENDING', 'message' => 'In progress', 'data' => []]),
    ]);
    tenancy()->initialize($this->client);
    $this->request->forceFill(['created_at' => now()->subHour()])->save();

    app(PaymentRequester::class)->request($this->invoice, '0713222333');

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/create-order-minimal')
        && base64_decode((string) $r['webhook']) === url('/cfco/payments/selcom/webhook'));
});

it('lets staff check a waiting payment from the invoice row', function () {
    Http::fake([CONFIRM_STATUS_URL => Http::response(selcomOrder($this->request->order_id, 'COMPLETED', ['transid' => 'T9', 'channel' => 'MPESA']))]);
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    Livewire::test(ListInvoices::class)
        ->assertActionVisible(TestAction::make('checkPayment')->table($this->invoice))
        ->callAction(TestAction::make('checkPayment')->table($this->invoice))
        ->assertNotified('Payment received');

    expect(Payment::query()->sole()->mobile_money_provider)->toBe(Payment::PROVIDER_MPESA);
});

it('maps Selcom channel names to our networks', function (?string $channel, ?string $provider) {
    expect(PaymentConfirmer::provider($channel))->toBe($provider);
})->with([
    ['TIGOPESATZ', Payment::PROVIDER_TIGOPESA],
    ['AIRTELMONEY', Payment::PROVIDER_AIRTELMONEY],
    ['MPESATZ', Payment::PROVIDER_MPESA],
    ['HALOPESA', Payment::PROVIDER_HALOPESA],
    ['MASTERPASS', null],
    [null, null],
]);
