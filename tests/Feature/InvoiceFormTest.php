<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Invoices\Pages\CreateInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->client = Client::create(['slug' => 'invco', 'name' => 'Inv Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Rehema',
        'email' => 'rehema@inv.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');
    $owner->forceFill(['onboarding_completed_at' => now()])->save();

    $this->actingAs($owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    $location = Location::create(['name' => 'Kimara', 'region' => 'Dar', 'district' => 'Ubungo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kimara Flats', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => 'K2', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 900_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'quarterly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Said Omari', 'phone' => '0715000222']);
    $this->lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-09-01', 'rent_amount' => 900_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'quarterly', 'payment_due_day' => 5,
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('fills the rent period, due date and rent line when the renter is picked', function () {
    Livewire::test(CreateInvoice::class)
        ->fillForm(['lease_id' => $this->lease->id])
        ->assertSchemaStateSet([
            'billing_period_start' => '2026-09-01',
            'billing_period_end' => '2026-11-30',
            'due_date' => '2026-09-05',
            'currency' => 'TZS',
        ])
        ->assertSee('TZS 900,000');

    $line = collect(Livewire::test(CreateInvoice::class)->fillForm(['lease_id' => $this->lease->id])->get('data.items'))->first();
    expect($line['description'])->toBe('Rent K2 · 01/09/2026 – 30/11/2026')
        ->and($line['type'])->toBe(InvoiceItem::TYPE_RENT)
        ->and($line['unit_price'])->toBe(900_000);
});

it('saves a draft with an extra charge and shows the running total', function () {
    $page = Livewire::test(CreateInvoice::class)->fillForm(['lease_id' => $this->lease->id]);

    $items = $page->get('data.items');
    $items[] = ['description' => 'Water, October', 'type' => InvoiceItem::TYPE_UTILITY, 'quantity' => 1, 'unit_price' => 25_000];

    $page->set('data.items', $items)
        ->assertSee('TZS 925,000')
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->sole();
    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->items()->count())->toBe(2)
        ->and($invoice->items()->where('type', InvoiceItem::TYPE_UTILITY)->sole()->unit_price)->toBe(25_000_00);
});

it('leaves rent off when this period is already billed, so it is never billed twice', function () {
    $billed = new Invoice([
        'lease_id' => $this->lease->id, 'currency' => 'TZS', 'billing_period_start' => '2026-09-01',
        'billing_period_end' => '2026-11-30', 'due_date' => '2026-09-05',
    ]);
    $billed->status = Invoice::STATUS_UNPAID;
    $billed->save();
    $billed->items()->create(['description' => 'Rent', 'type' => InvoiceItem::TYPE_RENT, 'quantity' => 1, 'unit_price' => 900_000_00]);

    $page = Livewire::test(CreateInvoice::class)->fillForm(['lease_id' => $this->lease->id]);

    expect($page->get('data.items'))->toBeEmpty();
});
