<?php

declare(strict_types=1);

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->plan = Plan::create([
        'name' => 'Starter', 'slug' => 'starter-shell', 'price_tzs' => 4_900_000, 'billing_period' => 'monthly',
        'max_properties' => 3, 'max_units' => 60, 'max_operators' => 6, 'features' => ['reports'],
    ]);
    $this->client = Client::create(['slug' => 'shellco', 'name' => 'Shell Rentals', 'status' => 'active', 'plan_id' => $this->plan->id]);
    $this->other = Client::create(['slug' => 'otherco', 'name' => 'Other Rentals', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina',
        'email' => 'amina@shell.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** Create a renter (and optionally an overdue invoice) inside a client's workspace. */
function shellRenter(Client $client, string $name, bool $overdue = false): Renter
{
    tenancy()->initialize($client);

    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => $name, 'phone' => '+2557'.random_int(10000000, 99999999)]);

    if ($overdue) {
        $location = Location::firstOrCreate(['name' => 'Loc '.$client->id], ['region' => 'Dar', 'district' => 'Ilala']);
        $property = Property::create(['location_id' => $location->id, 'name' => 'Block', 'status' => 'active']);
        $unit = Unit::create(['property_id' => $property->id, 'code' => 'U'.random_int(1, 999), 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 100_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
        $lease = Lease::create(['unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE, 'start_date' => now()->subMonths(2)->toDateString(), 'rent_amount' => 100_00, 'deposit_amount' => 0, 'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1]);
        $invoice = new Invoice(['lease_id' => $lease->id, 'currency' => 'TZS', 'billing_period_start' => now()->subMonth()->startOfMonth()->toDateString(), 'billing_period_end' => now()->subMonth()->endOfMonth()->toDateString(), 'due_date' => now()->subDays(10)->toDateString()]);
        $invoice->status = Invoice::STATUS_OVERDUE;
        $invoice->save();
    }

    Tenancy::end();

    return $renter;
}

it('orders the menu with daily work first and folds reports and website away', function () {
    expect(array_map(fn (NavGroup $g): string => $g->name, NavGroup::cases()))
        ->toBe(['Billing', 'RentersLeases', 'Properties', 'Reports', 'Website', 'SettingsTeam'])
        ->and(NavGroup::Reports->isCollapsed())->toBeTrue()
        ->and(NavGroup::Website->isCollapsed())->toBeTrue()
        ->and(NavGroup::Billing->isCollapsed())->toBeFalse();

    $this->actingAs($this->owner, 'web')->get('/manage')
        ->assertOk()
        ->assertSeeInOrder(['Today', 'Billing', 'Renters &amp; leases', 'Properties', 'Reports', 'Website', 'Settings &amp; team'], false);
});

it('shows the workspace name and plan usage instead of a generic brand', function () {
    $this->actingAs($this->owner, 'web')->get('/manage')
        ->assertOk()
        ->assertSee('Shell Rentals')
        ->assertDontSee('PMS Operator')
        ->assertSee('Starter plan')
        ->assertSee('0 of 60 units used');
});

it('counts only this workspace\'s overdue invoices in the menu badge', function () {
    shellRenter($this->client, 'Late Larry', overdue: true);
    shellRenter($this->other, 'Other Late', overdue: true);
    shellRenter($this->other, 'Other Late Two', overdue: true);

    $this->actingAs($this->owner, 'web');
    tenancy()->initialize($this->client);

    expect(InvoiceResource::getNavigationBadge())->toBe('1');
});

it('never returns another workspace\'s renters from search', function () {
    shellRenter($this->client, 'Juma Mine');
    shellRenter($this->other, 'Juma Theirs');

    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    $titles = RenterResource::getGlobalSearchResults('Juma')->map(fn ($r) => (string) $r->title)->all();

    expect($titles)->toBe(['Juma Mine']);
});
