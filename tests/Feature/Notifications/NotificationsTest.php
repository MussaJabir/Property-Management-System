<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\InvoiceOverdueNotification;
use App\Notifications\MaintenanceRequestSubmittedNotification;
use App\Notifications\OperatorCredentialsIssuedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Services\Admin\OperatorOwnerProvisioner;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create([
        'slug' => 'notif-client',
        'name' => 'Notif Co.',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Build property + unit + renter (with portal user) + ACTIVE lease.
 *
 * @return array{0: Renter, 1: Lease, 2: User}
 */
function setupBilling(): array
{
    $location = Location::firstOrCreate(
        ['name' => 'NLoc'],
        ['region' => 'Dar', 'district' => 'Kinondoni'],
    );
    $property = Property::create([
        'tenant_id' => tenant('id'),
        'location_id' => $location->id,
        'name' => 'NProp',
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'tenant_id' => tenant('id'),
        'property_id' => $property->id,
        'code' => 'NU1',
        'status' => Unit::STATUS_VACANT,
        'rent_amount' => 500_00,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
    ]);
    $renter = Renter::create([
        'tenant_id' => tenant('id'),
        'type' => Renter::TYPE_INDIVIDUAL,
        'full_name' => 'Notif Renter',
        'phone' => '+255712009001',
    ]);
    $lease = Lease::create([
        'tenant_id' => tenant('id'),
        'unit_id' => $unit->id,
        'renter_id' => $renter->id,
        'status' => Lease::STATUS_PENDING,
        'start_date' => now()->toDateString(),
        'rent_amount' => 500_00,
        'deposit_amount' => 0,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
    ]);
    $lease->activate(); // provisions portal user via Phase 8 hook

    return [$renter->fresh(), $lease->fresh(), $renter->fresh()->user];
}

it('provisions an operator owner with role + welcome email when filled', function () {
    Notification::fake();

    $user = app(OperatorOwnerProvisioner::class)
        ->provisionFor($this->client, 'Owner Person', 'owner@notif.local', '+255712000000');

    expect($user)->not->toBeNull();
    expect($user->type)->toBe(User::TYPE_OPERATOR);
    expect($user->must_change_password)->toBeTrue();
    expect($user->tenant_id)->toBe($this->client->id);

    Notification::assertSentTo($user, OperatorCredentialsIssuedNotification::class);
});

it('is idempotent: re-provisioning the same email returns the existing user', function () {
    Notification::fake();
    $svc = app(OperatorOwnerProvisioner::class);

    $first = $svc->provisionFor($this->client, 'Owner', 'owner@notif.local');
    $second = $svc->provisionFor($this->client, 'Owner', 'owner@notif.local');

    expect($second->id)->toBe($first->id);
});

it('fires InvoiceIssuedNotification when an invoice is issued', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    [$renter, $lease, $renterUser] = setupBilling();

    $invoice = Invoice::create([
        'tenant_id' => tenant('id'),
        'lease_id' => $lease->id,
        'status' => 'draft',
        'currency' => 'TZS',
        'billing_period_start' => now()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Rent',
        'quantity' => 1,
        'unit_price' => 500_00,
        'line_total' => 500_00,
    ]);

    $invoice->refresh()->issue();

    Notification::assertSentTo($renterUser, InvoiceIssuedNotification::class);
});

it('fires InvoiceOverdueNotification on overdue promotion', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    [$renter, $lease, $renterUser] = setupBilling();

    $invoice = Invoice::create([
        'tenant_id' => tenant('id'),
        'lease_id' => $lease->id,
        'status' => Invoice::STATUS_UNPAID,
        'currency' => 'TZS',
        'billing_period_start' => now()->subMonth()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->subMonth()->endOfMonth()->toDateString(),
        'due_date' => now()->subDays(10)->toDateString(),
        'total_amount' => 500_00,
        'amount_paid' => 0,
    ]);

    expect($invoice->markOverdueIfDue())->toBeTrue();
    Notification::assertSentTo($renterUser, InvoiceOverdueNotification::class);
});

it('fires PaymentReceivedNotification when a payment is recorded', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    [$renter, $lease, $renterUser] = setupBilling();

    $invoice = Invoice::create([
        'tenant_id' => tenant('id'),
        'lease_id' => $lease->id,
        'status' => Invoice::STATUS_UNPAID,
        'currency' => 'TZS',
        'billing_period_start' => now()->startOfMonth()->toDateString(),
        'billing_period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->addDays(5)->toDateString(),
        'total_amount' => 500_00,
        'amount_paid' => 0,
    ]);

    Payment::create([
        'tenant_id' => tenant('id'),
        'invoice_id' => $invoice->id,
        'amount' => 500_00,
        'currency' => 'TZS',
        'method' => 'cash',
        'payment_date' => now()->toDateString(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    Notification::assertSentTo($renterUser, PaymentReceivedNotification::class);
});

it('notifies operators with the right role when a maintenance request is created', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);

    $manager = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Manager',
        'email' => 'manager@notif.local',
        'phone' => '+255712009100',
        'password' => bcrypt('x'),
        'status' => 'active',
    ]);
    $manager->assignRole('manager');

    $accountant = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Accountant',
        'email' => 'accountant@notif.local',
        'phone' => '+255712009101',
        'password' => bcrypt('x'),
        'status' => 'active',
    ]);
    $accountant->assignRole('accountant');

    [$renter, $lease, $renterUser] = setupBilling();

    MaintenanceRequest::create([
        'tenant_id' => tenant('id'),
        'unit_id' => $lease->unit_id,
        'title' => 'Leaky tap',
        'description' => 'Kitchen tap drips constantly.',
        'priority' => MaintenanceRequest::PRIORITY_MEDIUM,
        'status' => MaintenanceRequest::STATUS_PENDING,
        'reported_at' => now(),
    ]);

    Notification::assertSentTo($manager, MaintenanceRequestSubmittedNotification::class);
    Notification::assertNotSentTo($accountant, MaintenanceRequestSubmittedNotification::class);
});

it('persists database notifications so the bell can read them later', function () {
    tenancy()->initialize($this->client);

    [$renter, $lease, $renterUser] = setupBilling();

    $renterUser->notify(new PaymentReceivedNotification(
        Payment::make([
            'tenant_id' => tenant('id'),
            'invoice_id' => '00000000-0000-0000-0000-000000000000',
            'amount' => 100_00,
            'currency' => 'TZS',
            'payment_date' => now()->toDateString(),
            'status' => Payment::STATUS_COMPLETED,
        ])
    ));

    expect($renterUser->notifications()->count())->toBe(1);
    expect($renterUser->unreadNotifications()->count())->toBe(1);
});
