<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Operator\Resources\Invoices\RelationManagers\PaymentRequestsRelationManager;
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
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    config(['services.selcom.sandbox_url' => 'https://selcom.test/api']);
    $this->travelTo(Carbon::parse('2026-10-19 12:00'));

    $this->client = Client::create(['slug' => 'chkco', 'name' => 'Check Co', 'status' => 'active']);

    $account = new SelcomAccount(['mode' => 'sandbox', 'vendor' => 'TILL01', 'api_key' => 'k', 'api_secret' => 's', 'enabled' => true, 'verified_at' => now()]);
    $account->forceFill(['tenant_id' => $this->client->id])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimara', 'region' => 'Dar', 'district' => 'Ubungo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Block', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'D4', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 100_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Juma Ali', 'phone' => '0714000111']);
    $lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-09-01', 'rent_amount' => 100_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    $this->invoice = new Invoice([
        'lease_id' => $lease->id, 'invoice_number' => 'INV-2026-000999', 'currency' => 'TZS',
        'subtotal' => 100_000_00, 'total_amount' => 100_000_00,
        'billing_period_start' => '2026-10-01', 'billing_period_end' => '2026-10-31', 'due_date' => '2026-10-01',
    ]);
    $this->invoice->status = Invoice::STATUS_OVERDUE;
    $this->invoice->save();
    Tenancy::end();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** A prompt sent $minutesAgo minutes ago. */
function waitingPrompt(Client $client, Invoice $invoice, int $minutesAgo, string $status = PaymentRequest::STATUS_PENDING, ?int $checkedMinutesAgo = null): PaymentRequest
{
    tenancy()->initialize($client);
    $request = PaymentRequest::create([
        'invoice_id' => $invoice->id, 'amount' => 100_000_00, 'currency' => 'TZS',
        'msisdn' => '255714000111', 'status' => $status,
    ]);
    $request->forceFill([
        'created_at' => now()->subMinutes($minutesAgo),
        'last_checked_at' => $checkedMinutesAgo !== null ? now()->subMinutes($checkedMinutesAgo) : null,
    ])->save();
    Tenancy::end();

    return $request;
}

function selcomSays(string $status): void
{
    Http::fake(['selcom.test/api/v1/checkout/order-status*' => function (Request $r) use ($status) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

        return Http::response([
            'resultcode' => '000', 'result' => 'SUCCESS', 'message' => 'Order fetch successful',
            'data' => [[
                'order_id' => $query['order_id'] ?? null, 'amount' => '100000', 'payment_status' => $status,
                'transid' => $status === 'COMPLETED' ? 'T77' : null, 'channel' => $status === 'COMPLETED' ? 'AIRTELMONEY' : null,
                'reference' => $status === 'COMPLETED' ? 'S77' : null,
            ]],
        ]);
    }]);
}

it('records a payment whose callback never came, once 3 minutes have passed', function () {
    $request = waitingPrompt($this->client, $this->invoice, minutesAgo: 4);
    selcomSays('COMPLETED');

    $this->artisan('payments:check-selcom')->expectsOutputToContain('1 payment(s) recorded')->assertSuccessful();

    tenancy()->initialize($this->client);
    expect($request->fresh()->status)->toBe(PaymentRequest::STATUS_COMPLETED)
        ->and(Payment::query()->sole()->mobile_money_provider)->toBe(Payment::PROVIDER_AIRTELMONEY)
        ->and($this->invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

it('waits 3 minutes before the first query and between queries', function () {
    waitingPrompt($this->client, $this->invoice, minutesAgo: 2);
    waitingPrompt($this->client, $this->invoice, minutesAgo: 10, checkedMinutesAgo: 1);
    Http::fake();

    $this->artisan('payments:check-selcom')->assertSuccessful();

    Http::assertNothingSent();
});

it('keeps a prompt waiting while Selcom says pending, then expires it after the order expires', function () {
    $young = waitingPrompt($this->client, $this->invoice, minutesAgo: 10);
    $old = waitingPrompt($this->client, $this->invoice, minutesAgo: 80);
    selcomSays('PENDING');

    $this->artisan('payments:check-selcom')->assertSuccessful();

    expect($young->fresh()->status)->toBe(PaymentRequest::STATUS_PENDING)
        ->and($young->fresh()->last_checked_at->equalTo(now()))->toBeTrue()
        ->and($old->fresh()->status)->toBe(PaymentRequest::STATUS_EXPIRED)
        ->and($old->fresh()->failure_reason)->toBe('Not paid before the prompt expired');
});

it('still records a late payment on an expired prompt', function () {
    $request = waitingPrompt($this->client, $this->invoice, minutesAgo: 120, status: PaymentRequest::STATUS_EXPIRED, checkedMinutesAgo: 31);
    selcomSays('COMPLETED');

    $this->artisan('payments:check-selcom')->assertSuccessful();

    expect($request->fresh()->status)->toBe(PaymentRequest::STATUS_COMPLETED)
        ->and($request->fresh()->failure_reason)->toBeNull();
});

it('records a late payment on an expired prompt when Selcom calls back', function () {
    $request = waitingPrompt($this->client, $this->invoice, minutesAgo: 120, status: PaymentRequest::STATUS_EXPIRED, checkedMinutesAgo: 1);
    selcomSays('COMPLETED');

    $this->postJson('/chkco/payments/selcom/webhook', ['order_id' => $request->order_id, 'payment_status' => 'COMPLETED'])
        ->assertJson(['status' => 'paid']);

    expect($request->fresh()->status)->toBe(PaymentRequest::STATUS_COMPLETED);
});

it('closes an expired prompt the payer cancelled, and stops looking after a day', function () {
    $cancelled = waitingPrompt($this->client, $this->invoice, minutesAgo: 120, status: PaymentRequest::STATUS_EXPIRED);
    $ancient = waitingPrompt($this->client, $this->invoice, minutesAgo: 25 * 60, status: PaymentRequest::STATUS_EXPIRED);
    selcomSays('USERCANCELLED');

    $this->artisan('payments:check-selcom')->assertSuccessful();

    expect($cancelled->fresh()->status)->toBe(PaymentRequest::STATUS_CANCELLED)
        ->and($ancient->fresh()->status)->toBe(PaymentRequest::STATUS_EXPIRED)
        ->and($ancient->fresh()->last_checked_at)->toBeNull();
});

it('checks suspended workspaces too, since the money has already moved', function () {
    $this->client->update(['status' => 'suspended']);
    $request = waitingPrompt($this->client, $this->invoice, minutesAgo: 5);
    selcomSays('COMPLETED');

    $this->artisan('payments:check-selcom')->assertSuccessful();

    expect($request->fresh()->status)->toBe(PaymentRequest::STATUS_COMPLETED);
});

it('shows the prompts sent for an invoice on its page', function () {
    $request = waitingPrompt($this->client, $this->invoice, minutesAgo: 5);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Owner',
        'email' => 'owner@chk.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');
    $this->actingAs($owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    Livewire::test(PaymentRequestsRelationManager::class, ['ownerRecord' => $this->invoice, 'pageClass' => EditInvoice::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$request])
        ->assertSee('Waiting for payer')
        ->assertSee('+255714000111');
});
