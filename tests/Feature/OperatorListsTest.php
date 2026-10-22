<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Operator\Resources\Payments\Pages\ListPayments;
use App\Filament\Operator\Resources\Units\Pages\CreateUnit;
use App\Filament\Operator\Resources\Units\Pages\ListUnits;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'listco', 'name' => 'List Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Rehema',
        'email' => 'rehema@list.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');
    $owner->forceFill(['onboarding_completed_at' => now()])->save();

    $this->actingAs($owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    $location = Location::create(['name' => 'Njiro', 'region' => 'Arusha', 'district' => 'Arusha City']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Njiro Plaza', 'status' => 'active']);
    $this->frame = Unit::create([
        'property_id' => $this->property->id, 'code' => 'G1', 'type' => 'business_frame', 'status' => Unit::STATUS_OCCUPIED,
        'rent_amount' => 2_100_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'semi_annual',
    ]);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Said Omari', 'phone' => '0715000222']);
    $this->lease = Lease::create([
        'unit_id' => $this->frame->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-10-01', 'rent_amount' => 2_100_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'semi_annual', 'payment_due_day' => 1,
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function invoiceFor(Lease $lease, string $status): Invoice
{
    $invoice = new Invoice([
        'lease_id' => $lease->id, 'currency' => 'TZS', 'billing_period_start' => '2026-10-01',
        'billing_period_end' => '2027-03-31', 'due_date' => '2026-10-01',
    ]);
    $invoice->status = $status;
    $invoice->save();
    $invoice->items()->create(['description' => 'Rent', 'type' => InvoiceItem::TYPE_RENT, 'quantity' => 1, 'unit_price' => 2_100_000_00]);

    return $invoice;
}

it('opens an issued invoice read-only, without Delete', function () {
    $invoice = invoiceFor($this->lease, Invoice::STATUS_UNPAID);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertActionHidden('delete')
        ->assertSchemaComponentStateSet('due_date', '2026-10-01')
        ->fillForm(['due_date' => '2026-12-01'])
        ->call('save');

    expect($invoice->fresh()->due_date->toDateString())->toBe('2026-10-01');
});

it('still lets staff edit and delete a draft invoice', function () {
    $invoice = invoiceFor($this->lease, Invoice::STATUS_DRAFT);

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertActionVisible('delete')
        ->fillForm(['due_date' => '2026-12-01'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($invoice->fresh()->due_date->toDateString())->toBe('2026-12-01');
});

it('shows a short-stay room\'s nightly rate in the units list, not TZS 0', function () {
    Unit::create([
        'property_id' => $this->property->id, 'code' => 'R101', 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 65_000_00, 'rent_amount' => 0,
        'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
    ]);

    Livewire::test(ListUnits::class)
        ->assertSeeText('TZS 65,000 / night')
        ->assertSeeText('Nightly')
        ->assertSeeText('TZS 2,100,000 / 6 months')
        ->assertDontSeeText('TZS 0');
});

it('keeps the property on "Save & add another"', function () {
    Livewire::test(CreateUnit::class)
        ->fillForm(['property_id' => $this->property->id, 'code' => 'L201', 'type' => 'room', 'monthly_rent' => 250_000])
        ->call('create', another: true)
        ->assertHasNoFormErrors()
        ->assertSchemaStateSet(['property_id' => $this->property->id, 'code' => null]);
});

it('shows the M-Pesa transaction ID in the payments list and finds it', function () {
    $invoice = invoiceFor($this->lease, Invoice::STATUS_UNPAID);
    Payment::create([
        'invoice_id' => $invoice->id, 'amount' => 1_000_000_00, 'currency' => 'TZS', 'payment_date' => '2026-10-02',
        'method' => Payment::METHOD_MOBILE_MONEY, 'mobile_money_provider' => 'mpesa', 'transaction_id' => 'TQ8K2L9XQ',
        'status' => 'completed',
    ]);

    Livewire::test(ListPayments::class)
        ->assertSeeText('TQ8K2L9XQ')
        ->assertSeeText('M-Pesa')
        ->searchTable('TQ8K2L9XQ')
        ->assertCountTableRecords(1);
});
