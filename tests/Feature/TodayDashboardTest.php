<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Payments\Pages\CreatePayment;
use App\Filament\Operator\Widgets\CollectionsChartWidget;
use App\Filament\Operator\Widgets\ComingUpWidget;
use App\Filament\Operator\Widgets\NeedsAttentionWidget;
use App\Filament\Operator\Widgets\RecentPaymentsWidget;
use App\Filament\Operator\Widgets\WorkspaceOverviewWidget;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'todayco', 'name' => 'Today Rentals', 'status' => 'active']);
    $this->other = Client::create(['slug' => 'elseco', 'name' => 'Else Rentals', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Amina Juma',
        'email' => 'amina@today.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Seed one workspace: an occupied unit whose renter owes an overdue invoice,
 * a lease ending in two weeks, a vacant unit and an open repair.
 *
 * @return array{invoice: Invoice, renter: Renter}
 */
function todayWorkspace(Client $client, string $prefix): array
{
    tenancy()->initialize($client);

    $location = Location::create(['name' => $prefix.' Area', 'region' => 'Dar', 'district' => 'Ilala']);
    $property = Property::create(['location_id' => $location->id, 'name' => $prefix.' Block', 'status' => 'active']);
    $unit = Unit::create(['property_id' => $property->id, 'code' => $prefix.'-A1', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 500_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    Unit::create(['property_id' => $property->id, 'code' => $prefix.'-EMPTY', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);

    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => $prefix.' Renter', 'phone' => '+2557'.random_int(10000000, 99999999)]);
    $lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => now()->subMonths(11)->toDateString(), 'end_date' => now()->addDays(14)->toDateString(),
        'rent_amount' => 500_000_00, 'deposit_amount' => 0, 'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);

    $invoice = new Invoice([
        'lease_id' => $lease->id, 'currency' => 'TZS', 'subtotal' => 500_000_00, 'total_amount' => 500_000_00,
        'billing_period_start' => now()->subMonth()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->subMonth()->endOfMonth()->toDateString(),
        'due_date' => now()->subDays(10)->toDateString(),
    ]);
    $invoice->status = Invoice::STATUS_OVERDUE;
    $invoice->save();

    MaintenanceRequest::create([
        'unit_id' => $unit->id, 'title' => $prefix.' leaking tap', 'description' => 'Drips all night.', 'priority' => MaintenanceRequest::PRIORITY_URGENT,
        'status' => MaintenanceRequest::STATUS_PENDING, 'reported_at' => now()->subDay(),
    ]);

    Tenancy::end();

    return ['invoice' => $invoice, 'renter' => $renter];
}

function actInWorkspace(User $user, Client $client): void
{
    test()->actingAs($user, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($client);
}

it('greets the operator and puts the everyday actions in the header', function () {
    $this->travelTo(now()->setTime(9, 0));

    $this->actingAs($this->owner, 'web')->get('/manage')
        ->assertOk()
        ->assertSee('Good morning, Amina')
        ->assertSee('Record payment')
        ->assertSee('Add renter')
        ->assertSee('New invoice')
        ->assertSee('/manage/invoices/create', false)
        // Primary buttons are deep teal with white text (shade 600, hover
        // 500), not the light fallback Filament uses when contrast fails.
        ->assertSee('fi-bg-color-600 hover:fi-bg-color-500', false);
});

it('lists only this workspace\'s overdue rent and repairs, with the next action', function () {
    ['invoice' => $invoice] = todayWorkspace($this->client, 'Mine');
    todayWorkspace($this->other, 'Theirs');

    actInWorkspace($this->owner, $this->client);

    Livewire::test(NeedsAttentionWidget::class)
        ->assertSee('Mine Renter')
        ->assertSee('10 days late')
        ->assertActionVisible(TestAction::make('recordPayment')->arguments(['invoice' => $invoice->id]))
        ->assertSee('Mine leaking tap')
        ->assertSee('Urgent')
        ->assertDontSee('Theirs Renter')
        ->assertDontSee('Theirs leaking tap');
});

it('shows leases ending soon and vacant units from this workspace only', function () {
    todayWorkspace($this->client, 'Mine');
    todayWorkspace($this->other, 'Theirs');

    actInWorkspace($this->owner, $this->client);

    Livewire::test(ComingUpWidget::class)
        ->assertSee('Mine Renter')
        ->assertSee(now()->addDays(14)->format('d/m/Y'))
        ->assertSee('Mine-EMPTY')
        ->assertSee('TZS 300,000')
        ->assertDontSee('Theirs');
});

it('sums the key figures and counts renters past due', function () {
    todayWorkspace($this->client, 'Mine');
    todayWorkspace($this->other, 'Theirs');

    actInWorkspace($this->owner, $this->client);

    Livewire::test(WorkspaceOverviewWidget::class)
        ->assertSee('TZS 500,000')
        ->assertSee('1 renter past due')
        ->assertSee('1 of 2 units occupied');
});

it('shows the latest payments with their mobile money network', function () {
    ['invoice' => $invoice] = todayWorkspace($this->client, 'Mine');

    actInWorkspace($this->owner, $this->client);
    Payment::create([
        'invoice_id' => $invoice->id, 'amount' => 200_000_00, 'currency' => 'TZS', 'payment_date' => now()->toDateString(),
        'method' => Payment::METHOD_MOBILE_MONEY, 'mobile_money_provider' => Payment::PROVIDER_MPESA, 'status' => Payment::STATUS_COMPLETED,
    ]);

    Livewire::test(RecentPaymentsWidget::class)
        ->assertSee('Mine Renter')
        ->assertSee('M-Pesa')
        ->assertSee('TZS 200,000');
});

it('opens Record payment with the invoice and its balance filled in', function () {
    ['invoice' => $invoice] = todayWorkspace($this->client, 'Mine');

    actInWorkspace($this->owner, $this->client);

    Livewire::withQueryParams(['invoice' => $invoice->id])
        ->test(CreatePayment::class)
        ->assertSchemaStateSet([
            'invoice_id' => $invoice->id,
            'currency' => 'TZS',
            'amount' => 500_000,
        ]);
});

it('ignores another workspace\'s invoice in the Record payment link', function () {
    ['invoice' => $theirs] = todayWorkspace($this->other, 'Theirs');

    actInWorkspace($this->owner, $this->client);

    Livewire::withQueryParams(['invoice' => $theirs->id])
        ->test(CreatePayment::class)
        ->assertSchemaStateSet(['invoice_id' => null]);
});

it('shows maintenance staff their repairs but not rent or billing actions', function () {
    todayWorkspace($this->client, 'Mine');

    $staff = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Baraka Fundi',
        'email' => 'baraka@today.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $staff->assignRole('maintenance-staff');
    $staff->forceFill(['onboarding_completed_at' => now()])->save();

    // (The onboarding tour mentions "Record payments", so check the links.)
    $this->actingAs($staff, 'web')->get('/manage')
        ->assertOk()
        ->assertDontSee('/manage/payments/create', false)
        ->assertDontSee('/manage/invoices/create', false);

    actInWorkspace($staff, $this->client);

    Livewire::test(NeedsAttentionWidget::class)
        ->assertSee('Mine leaking tap')
        ->assertDontSee('Overdue rent')
        ->assertDontSee('Mine Renter');

    // Read-only inventory: the vacant unit is listed but not linked to its edit page.
    Livewire::test(ComingUpWidget::class)
        ->assertSee('Mine-EMPTY')
        ->assertDontSee('/edit', false);

    expect(WorkspaceOverviewWidget::canView())->toBeFalse()
        ->and(RecentPaymentsWidget::canView())->toBeFalse()
        ->and(CollectionsChartWidget::canView())->toBeFalse();
});
