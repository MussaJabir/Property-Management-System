<?php

use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Reports\Builders\ExpenseReport;
use App\Reports\Builders\MonthlyRentCollectionReport;
use App\Reports\Builders\OccupancyReport;
use App\Reports\Builders\OutstandingRentReport;
use App\Reports\Builders\ProfitSummaryReport;
use App\Reports\Builders\PropertyIncomeReport;
use App\Reports\Builders\RenterPaymentHistoryReport;
use App\Services\Reports\ReportPdfGenerator;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->clientA = Client::create(['slug' => 'a', 'name' => 'Client A', 'status' => 'active']);
    $this->clientB = Client::create(['slug' => 'b', 'name' => 'Client B', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Build the full graph (property + unit + renter + active lease) inside the
 * active tenant. Returns [property, unit, renter, lease].
 *
 * @return array{0: Property, 1: Unit, 2: Renter, 3: Lease}
 */
function makePropertyGraph(string $unitCode = 'U1', int $rentCents = 500_000_00): array
{
    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $property = Property::create([
        'location_id' => $loc->id,
        'name' => 'Tower '.$unitCode,
        'type' => 'residential',
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'property_id' => $property->id,
        'code' => $unitCode,
        'type' => 'room',
        'rent_amount' => $rentCents,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'status' => 'vacant',
    ]);
    $renter = Renter::create([
        'type' => 'individual',
        'full_name' => 'Renter '.$unitCode,
        'phone' => '+255712345678',
    ]);
    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => $rentCents,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'pending',
    ]);
    $lease->activate();

    return [$property, $unit, $renter, $lease];
}

function makeIssuedInvoice(Lease $lease, int $totalCents, ?Carbon $dueDate = null): Invoice
{
    $invoice = Invoice::create([
        'lease_id' => $lease->id,
        'billing_period_start' => now()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => ($dueDate ?? now()->addDays(7))->toDateString(),
        'currency' => 'TZS',
        'status' => 'draft',
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Rent',
        'type' => 'rent',
        'quantity' => 1,
        'unit_price' => $totalCents,
    ]);
    $invoice->refresh()->issue();

    return $invoice->fresh();
}

it('builds the monthly rent collection report grouped by month', function () {
    Tenancy::initialize($this->clientA);
    [, , , $lease] = makePropertyGraph();
    $invoice = makeIssuedInvoice($lease, 500_000_00);

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 500_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $report = new MonthlyRentCollectionReport(
        from: now()->startOfMonth(),
        to: now()->endOfMonth(),
    );

    $rows = $report->rows();
    expect($rows)->toHaveCount(1);
    expect($rows->first()['total'])->toBe('500,000');
    expect($report->summary()['Total collected'])->toBe('TZS 500,000');
});

it('lists open invoices in the outstanding rent report', function () {
    Tenancy::initialize($this->clientA);
    [, , , $lease] = makePropertyGraph();

    // Paid invoice — should NOT appear.
    $paid = makeIssuedInvoice($lease, 100_000_00);
    Payment::create([
        'invoice_id' => $paid->id,
        'amount' => 100_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    // Unpaid invoice — should appear.
    $unpaid = makeIssuedInvoice($lease, 300_000_00);

    $report = new OutstandingRentReport;

    $rows = $report->rows();
    expect($rows)->toHaveCount(1);
    expect($rows->first()['balance'])->toBe('300,000');
    expect($report->summary()['Total outstanding'])->toBe('TZS 300,000');
});

it('reports occupancy per property and a portfolio total', function () {
    Tenancy::initialize($this->clientA);

    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $property = Property::create([
        'location_id' => $loc->id,
        'name' => 'Tower X',
        'type' => 'residential',
        'status' => 'active',
    ]);
    Unit::create(['property_id' => $property->id, 'code' => 'A1', 'type' => 'room', 'rent_amount' => 0, 'status' => 'occupied']);
    Unit::create(['property_id' => $property->id, 'code' => 'A2', 'type' => 'room', 'rent_amount' => 0, 'status' => 'occupied']);
    Unit::create(['property_id' => $property->id, 'code' => 'A3', 'type' => 'room', 'rent_amount' => 0, 'status' => 'vacant']);
    Unit::create(['property_id' => $property->id, 'code' => 'A4', 'type' => 'room', 'rent_amount' => 0, 'status' => 'maintenance']);

    $report = new OccupancyReport;
    $rows = $report->rows();

    expect($rows->first()['total'])->toBe('4');
    expect($rows->first()['occupied'])->toBe('2');
    expect($rows->first()['vacant'])->toBe('1');
    expect($rows->first()['occupancy'])->toBe('50%');
    expect($report->summary()['Portfolio occupancy'])->toBe('50%');
});

it('attributes income to the right property in the property income report', function () {
    Tenancy::initialize($this->clientA);

    [$property, , , $lease] = makePropertyGraph('U1', 200_000_00);
    $invoice = makeIssuedInvoice($lease, 200_000_00);
    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 200_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $report = new PropertyIncomeReport(
        from: now()->startOfMonth(),
        to: now()->endOfMonth(),
    );

    $rows = $report->rows();
    $row = $rows->firstWhere('property', $property->name);

    expect($row)->not->toBeNull();
    expect($row['collected'])->toBe('200,000');
});

it('lists expenses and totals them per category in the expense report', function () {
    Tenancy::initialize($this->clientA);

    [$property] = makePropertyGraph();
    $repair = ExpenseCategory::where('name', 'Repair')->firstOrFail();
    $cleaning = ExpenseCategory::where('name', 'Cleaning')->firstOrFail();

    Expense::create([
        'property_id' => $property->id, 'category_id' => $repair->id,
        'amount' => 50_000_00, 'currency' => 'TZS',
        'expense_date' => now()->toDateString(),
    ]);
    Expense::create([
        'property_id' => $property->id, 'category_id' => $cleaning->id,
        'amount' => 20_000_00, 'currency' => 'TZS',
        'expense_date' => now()->toDateString(),
    ]);

    $report = new ExpenseReport(
        from: now()->startOfMonth(),
        to: now()->endOfMonth(),
    );

    expect($report->rows())->toHaveCount(2);
    expect($report->summary()['Repair'])->toBe('TZS 50,000');
    expect($report->summary()['Cleaning'])->toBe('TZS 20,000');
    expect($report->summary()['Total expenses'])->toBe('TZS 70,000');
});

it('subtracts expenses from income in the profit summary', function () {
    Tenancy::initialize($this->clientA);

    [$property, , , $lease] = makePropertyGraph('U1', 1_000_000_00);
    $invoice = makeIssuedInvoice($lease, 1_000_000_00);
    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 1_000_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    $repair = ExpenseCategory::where('name', 'Repair')->firstOrFail();
    Expense::create([
        'property_id' => $property->id, 'category_id' => $repair->id,
        'amount' => 250_000_00, 'currency' => 'TZS',
        'expense_date' => now()->toDateString(),
    ]);

    $report = new ProfitSummaryReport(
        from: now()->startOfMonth(),
        to: now()->endOfMonth(),
    );

    $summary = $report->summary();
    expect($summary['Total income'])->toBe('TZS 1,000,000');
    expect($summary['Total expenses'])->toBe('TZS 250,000');
    expect($summary['Net profit'])->toBe('TZS 750,000');
});

it('lists every payment for a single renter in the renter payment history', function () {
    Tenancy::initialize($this->clientA);

    [, , $renter, $lease] = makePropertyGraph();
    $invoice = makeIssuedInvoice($lease, 300_000_00);
    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 300_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'mobile_money',
        'mobile_money_provider' => 'mpesa',
        'reference_number' => 'TXN-001',
        'status' => 'completed',
    ]);

    $report = new RenterPaymentHistoryReport($renter->id);

    expect($report->rows())->toHaveCount(1);
    expect($report->rows()->first()['amount'])->toBe('300,000');
    expect($report->summary()['Total paid (lifetime)'])->toBe('TZS 300,000');
});

it('keeps reports isolated between clients', function () {
    Tenancy::initialize($this->clientA);
    [, , , $leaseA] = makePropertyGraph();
    $invoice = makeIssuedInvoice($leaseA, 500_000_00);
    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => 500_000_00,
        'currency' => 'TZS',
        'payment_date' => now()->toDateString(),
        'method' => 'cash',
        'status' => 'completed',
    ]);

    Tenancy::end();
    Tenancy::initialize($this->clientB);

    $report = new MonthlyRentCollectionReport(
        from: now()->startOfMonth(),
        to: now()->endOfMonth(),
    );

    expect($report->rows())->toHaveCount(0);
    expect($report->summary()['Total collected'])->toBe('TZS 0');
});

it('renders any report as a non-empty PDF', function () {
    Tenancy::initialize($this->clientA);
    makePropertyGraph();

    $bytes = app(ReportPdfGenerator::class)->render(new OccupancyReport);

    expect(substr($bytes, 0, 5))->toBe('%PDF-');
    expect(strlen($bytes))->toBeGreaterThan(1000);
})->skip(getenv('SKIP_PDF_TESTS') === '1', 'PDF tests disabled by SKIP_PDF_TESTS=1');
