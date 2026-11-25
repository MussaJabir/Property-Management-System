<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\Reports\ArrearsAgeingPage;
use App\Filament\Operator\Pages\Reports\LeaseExpiryPage;
use App\Filament\Operator\Pages\Reports\RentRollPage;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Reports\Builders\ArrearsAgeingReport;
use App\Reports\Builders\LeaseExpiryReport;
use App\Reports\Builders\RentRollReport;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-24 09:00'));
    $this->client = Client::create(['slug' => 'rollco', 'name' => 'Roll Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@roll.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);

    // A3: Jean, ends in 20 days, owes 3 invoices of different ages.
    $this->jean = rollLease('A3', 'Jean Uwase', '2026-01-01', today()->addDays(20)->toDateString());
    rollInvoice($this->jean, 300_000_00, '2026-08-05'); // 111 days
    rollInvoice($this->jean, 300_000_00, '2026-10-05'); // 50 days
    rollInvoice($this->jean, 300_000_00, '2026-11-05'); // 19 days
    // B1: Patrick, ends in 75 days, all paid.
    $this->patrick = rollLease('B1', 'Patrick Nshuti', '2026-03-01', today()->addDays(75)->toDateString());
    $paid = rollInvoice($this->patrick, 450_000_00, '2026-11-01');
    Payment::create(['invoice_id' => $paid->id, 'amount' => 450_000_00, 'currency' => 'TZS', 'payment_date' => '2026-11-02', 'method' => Payment::METHOD_CASH, 'status' => Payment::STATUS_COMPLETED]);
    // C4: empty.
    Unit::create(['property_id' => $this->property->id, 'code' => 'C4', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 200_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function rollLease(string $code, string $name, string $start, string $end): Lease
{
    $unit = Unit::create(['property_id' => test()->property->id, 'code' => $code, 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => $name, 'phone' => '+2557'.random_int(10000000, 99999999)]);

    return Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => $start, 'end_date' => $end, 'rent_amount' => $code === 'B1' ? 450_000_00 : 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 5, 'notice_days' => 30,
    ]);
}

function rollInvoice(Lease $lease, int $amount, string $due): Invoice
{
    $invoice = Invoice::create(['lease_id' => $lease->id, 'status' => Invoice::STATUS_DRAFT, 'currency' => 'TZS', 'billing_period_start' => $due, 'billing_period_end' => Carbon::parse($due)->endOfMonth()->toDateString(), 'due_date' => $due]);
    $invoice->items()->create(['description' => 'Rent', 'type' => 'rent', 'quantity' => 1, 'unit_price' => $amount]);
    $invoice->refresh()->issue();

    return $invoice->fresh();
}

it('lists every unit with its tenant, rent and what is owed', function () {
    $report = new RentRollReport;
    $rows = $report->rows()->keyBy('unit');

    expect($rows)->toHaveCount(3)
        ->and($rows['Kigali Heights / A3']['tenant'])->toBe('Jean Uwase')
        ->and($rows['Kigali Heights / A3']['owed'])->toBe('900,000')
        ->and($rows['Kigali Heights / B1']['owed'])->toBe('—')
        ->and($rows['Kigali Heights / B1']['last_paid'])->toBe('02/11/2026')
        ->and($rows['Kigali Heights / C4']['status'])->toBe('Vacant')
        ->and($report->summary()['Let'])->toBe('2 (67%)')
        ->and($report->summary()['Rent a month'])->toBe('TZS 750,000')
        ->and($report->summary()['Owed'])->toBe('TZS 900,000');
});

it('shows leases ending soon and whether they are renewed', function () {
    $this->patrick->renew(['start_date' => $this->patrick->end_date->copy()->addDay()->toDateString(), 'end_date' => null, 'rent_amount' => 450_000_00, 'deposit_amount' => 0, 'carry_deposit' => false, 'activate' => false]);

    $within30 = new LeaseExpiryReport(days: 30);
    expect($within30->rows()->pluck('tenant')->all())->toBe(['Jean Uwase']);

    $within90 = new LeaseExpiryReport(days: 90);
    $rows = $within90->rows()->keyBy('tenant');
    expect($rows)->toHaveCount(2)
        ->and($rows['Jean Uwase']['days_left'])->toBe('20')
        ->and($rows['Jean Uwase']['renewal'])->toBe('Not yet')
        ->and($rows['Patrick Nshuti']['renewal'])->toStartWith('From ')
        ->and($within90->summary())->toBe(['In 30 days' => '1', 'In 60 days' => '1', 'In 90 days' => '2', 'Not yet renewed' => '1']);
});

it('splits what each tenant owes by how long it is overdue', function () {
    $report = new ArrearsAgeingReport;
    $jean = $report->rows()->sole();

    expect($jean['tenant'])->toBe('Jean Uwase')
        ->and($jean['b1–30'])->toBe('300,000')
        ->and($jean['b31–60'])->toBe('300,000')
        ->and($jean['b61–90'])->toBe('')
        ->and($jean['b90+'])->toBe('300,000')
        ->and($jean['total'])->toBe('900,000')
        ->and($report->summary()['Total owed'])->toBe('TZS 900,000')
        ->and(ArrearsAgeingReport::bucketFor(30))->toBe('1–30')
        ->and(ArrearsAgeingReport::bucketFor(31))->toBe('31–60')
        ->and(ArrearsAgeingReport::bucketFor(91))->toBe('90+');

    // As of an earlier date the debts are younger.
    $earlier = (new ArrearsAgeingReport(asOf: Carbon::parse('2026-10-10')))->rows()->sole();
    expect($earlier['b1–30'])->toBe('300,000')
        ->and($earlier['b61–90'])->toBe('300,000')
        ->and($earlier['total'])->toBe('600,000');
});

it('opens the three reports for staff with report rights', function () {
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(RentRollPage::class)->assertOk()->assertSee('Jean Uwase');
    Livewire::test(LeaseExpiryPage::class)->assertOk()->assertSee('Jean Uwase');
    Livewire::test(ArrearsAgeingPage::class)->assertOk()->assertSee('900,000');
});
