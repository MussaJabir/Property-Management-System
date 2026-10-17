<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Invoices\Pages\ListInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentInvoiceGenerator;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'rentco', 'name' => 'Rent Co', 'status' => 'active']);
    $this->other = Client::create(['slug' => 'otherrent', 'name' => 'Other Rent', 'status' => 'active']);
    $this->travelTo(Carbon::parse('2026-10-17 09:00'));
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** A lease in $client's workspace; leaves tenancy initialised for $client. */
function rentLease(Client $client, array $attrs = []): Lease
{
    tenancy()->initialize($client);

    $location = Location::firstOrCreate(['name' => 'Area '.$client->id], ['region' => 'Dar', 'district' => 'Ilala']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Block', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'A'.random_int(1, 999), 'status' => Unit::STATUS_VACANT, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Neema Mushi', 'phone' => '+2557'.random_int(10000000, 99999999)]);

    return Lease::create(array_merge([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-07-01', 'rent_amount' => 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => Lease::BILLING_MONTHLY, 'payment_due_day' => 5,
    ], $attrs));
}

it('issues the current month\'s rent invoice once, numbered and due on the lease due day', function () {
    $lease = rentLease($this->client);
    $generator = app(RentInvoiceGenerator::class);

    $invoice = $generator->generateFor($lease);

    expect($invoice)->not->toBeNull()
        ->and($invoice?->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice?->invoice_number)->not->toBeEmpty()
        ->and($invoice?->billing_period_start?->toDateString())->toBe('2026-10-01')
        ->and($invoice?->billing_period_end?->toDateString())->toBe('2026-10-31')
        ->and($invoice?->due_date?->toDateString())->toBe('2026-10-05')
        ->and($invoice?->total_amount)->toBe(300_000_00)
        // Not the first period, so no deposit line.
        ->and($invoice?->items->pluck('type')->all())->toBe([InvoiceItem::TYPE_RENT]);

    // Second run (scheduler, or someone pressing the button) is a no-op.
    expect($generator->generateFor($lease))->toBeNull()
        ->and(Invoice::query()->where('lease_id', $lease->id)->count())->toBe(1);
});

it('skips a period already covered by a hand-made invoice, but not a cancelled one', function () {
    $lease = rentLease($this->client, ['start_date' => '2026-07-15']);

    // Calendar-month invoice made by hand overlaps the 15 Oct – 14 Nov period.
    $manual = Invoice::create([
        'lease_id' => $lease->id, 'status' => Invoice::STATUS_DRAFT, 'currency' => 'TZS',
        'billing_period_start' => '2026-10-01', 'billing_period_end' => '2026-10-31', 'due_date' => '2026-10-07',
    ]);

    expect(app(RentInvoiceGenerator::class)->generateFor($lease))->toBeNull();

    $manual->cancel();
    $invoice = app(RentInvoiceGenerator::class)->generateFor($lease);

    expect($invoice?->billing_period_start?->toDateString())->toBe('2026-10-15')
        ->and($invoice?->billing_period_end?->toDateString())->toBe('2026-11-14')
        // Due day 5 falls before the period starts, so it's due on day one.
        ->and($invoice?->due_date?->toDateString())->toBe('2026-10-15');
});

it('bills quarterly leases per quarter and stops the last period at the lease end', function () {
    $lease = rentLease($this->client, [
        'start_date' => '2026-02-01', 'end_date' => '2026-11-30',
        'billing_cycle' => Lease::BILLING_QUARTERLY, 'rent_amount' => 900_000_00,
    ]);

    $invoice = app(RentInvoiceGenerator::class)->generateFor($lease);

    expect($invoice?->billing_period_start?->toDateString())->toBe('2026-08-01')
        ->and($invoice?->billing_period_end?->toDateString())->toBe('2026-10-31')
        ->and($invoice?->total_amount)->toBe(900_000_00);

    $this->travelTo(Carbon::parse('2026-11-02'));
    $last = app(RentInvoiceGenerator::class)->generateFor($lease);
    expect($last?->billing_period_end?->toDateString())->toBe('2026-11-30');

    $this->travelTo(Carbon::parse('2026-12-01'));
    expect(app(RentInvoiceGenerator::class)->generateFor($lease))->toBeNull();
});

it('handles leases that start on the 31st', function () {
    $lease = rentLease($this->client, ['start_date' => '2026-01-31']);
    $this->travelTo(Carbon::parse('2026-03-01'));

    $period = app(RentInvoiceGenerator::class)->currentPeriod($lease);

    expect($period['start']->toDateString())->toBe('2026-02-28')
        ->and($period['end']->toDateString())->toBe('2026-03-30');
});

it('invoices the first period with the deposit as soon as a lease is activated', function () {
    $lease = rentLease($this->client, [
        'status' => Lease::STATUS_PENDING, 'start_date' => '2026-10-17', 'deposit_amount' => 600_000_00,
    ]);

    $lease->activate();

    $invoice = Invoice::query()->where('lease_id', $lease->id)->with('items')->sole();
    expect($invoice->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice->total_amount)->toBe(900_000_00)
        ->and($invoice->items->pluck('type')->sort()->values()->all())->toBe([InvoiceItem::TYPE_DEPOSIT, InvoiceItem::TYPE_RENT]);
});

it('runs daily for every workspace that has automatic invoices on', function () {
    $mine = rentLease($this->client);
    Tenancy::end();
    $theirs = rentLease($this->other);
    Tenancy::end();
    $this->other->update(['settings' => ['auto_invoices' => false]]);

    $this->artisan('billing:generate-rent-invoices')->assertSuccessful();
    $this->artisan('billing:generate-rent-invoices')->assertSuccessful();

    expect(Invoice::withoutGlobalScopes()->where('lease_id', $mine->id)->count())->toBe(1)
        ->and(Invoice::withoutGlobalScopes()->where('lease_id', $theirs->id)->count())->toBe(0)
        ->and(Invoice::withoutGlobalScopes()->where('lease_id', $mine->id)->value('tenant_id'))->toBe($this->client->id);
});

it('does not invoice on activation when automatic invoices are off', function () {
    $this->client->update(['settings' => ['auto_invoices' => false]]);
    $lease = rentLease($this->client, ['status' => Lease::STATUS_PENDING, 'start_date' => '2026-10-17']);

    $lease->activate();

    expect(Invoice::query()->where('lease_id', $lease->id)->exists())->toBeFalse();
});

it('lets billing staff create this period\'s invoices from the Invoices page', function () {
    rentLease($this->client);
    rentLease($this->client);
    Tenancy::end();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina',
        'email' => 'amina@rent.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');

    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    $this->actingAs($owner, 'web');
    Livewire::test(ListInvoices::class)
        ->callAction('generateRentInvoices')
        ->assertNotified('2 rent invoices created');

    Livewire::test(ListInvoices::class)
        ->callAction('generateRentInvoices')
        ->assertNotified('Every active lease is already invoiced for this period');

    expect(Invoice::query()->count())->toBe(2);
});
