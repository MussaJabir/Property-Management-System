<?php

use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\Property;
use Illuminate\Database\QueryException;
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

function makeProperty(): Property
{
    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);

    return Property::create([
        'location_id' => $loc->id,
        'name' => 'P',
        'type' => 'residential',
        'status' => 'active',
    ]);
}

it('records an expense against a property and formats the amount', function () {
    Tenancy::initialize($this->clientA);

    $prop = makeProperty();
    $cat = ExpenseCategory::where('name', 'Repair')->firstOrFail();

    $expense = Expense::create([
        'property_id' => $prop->id,
        'category_id' => $cat->id,
        'amount' => 75_000_00,
        'currency' => 'TZS',
        'expense_date' => now()->toDateString(),
        'description' => 'Plumber call-out',
    ]);

    expect($expense->fresh()->formatted_amount)->toBe('TZS 75,000');
    expect($expense->category->name)->toBe('Repair');
    expect($expense->property->name)->toBe('P');
});

it('allows an expense with no property (general overhead)', function () {
    Tenancy::initialize($this->clientA);

    $cat = ExpenseCategory::where('name', 'Tax')->firstOrFail();

    $expense = Expense::create([
        'category_id' => $cat->id,
        'amount' => 10_000_00,
        'currency' => 'TZS',
        'expense_date' => now()->toDateString(),
    ]);

    expect($expense->property)->toBeNull();
    expect($expense->category->name)->toBe('Tax');
});

it('keeps expenses and categories isolated between clients', function () {
    Tenancy::initialize($this->clientA);
    $prop = makeProperty();
    $cat = ExpenseCategory::where('name', 'Cleaning')->firstOrFail();
    Expense::create([
        'property_id' => $prop->id,
        'category_id' => $cat->id,
        'amount' => 5_000_00,
        'currency' => 'TZS',
        'expense_date' => now()->toDateString(),
    ]);

    Tenancy::end();
    Tenancy::initialize($this->clientB);

    expect(Expense::count())->toBe(0);
    // Each client gets its own seeded copy of the six categories.
    expect(ExpenseCategory::count())->toBe(6);
});

it('keeps category names unique within a single client', function () {
    Tenancy::initialize($this->clientA);

    // Six defaults already; trying to add another "Repair" is rejected.
    expect(fn () => ExpenseCategory::create(['name' => 'Repair', 'color' => '#000000']))
        ->toThrow(QueryException::class);
});
