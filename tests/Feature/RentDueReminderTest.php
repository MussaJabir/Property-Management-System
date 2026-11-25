<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\RentDueReminderNotification;
use App\Support\RentReminders;
use Filament\Facades\Filament;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-26 08:00'));
    Notification::fake();
    $this->client = Client::create(['slug' => 'dueco', 'name' => 'Due Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@due.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function dueInvoice(string $code, ?string $email, string $dueDate, string $status = Invoice::STATUS_UNPAID): Invoice
{
    $unit = Unit::create(['property_id' => test()->property->id, 'code' => $code, 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => "Renter {$code}", 'phone' => '+2557'.random_int(10000000, 99999999), 'email' => $email]);
    $lease = Lease::create([
        'unit_id' => $unit->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => '2026-01-01', 'rent_amount' => 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 5,
    ]);
    $invoice = Invoice::create(['lease_id' => $lease->id, 'status' => Invoice::STATUS_DRAFT, 'currency' => 'TZS', 'billing_period_start' => '2026-12-01', 'billing_period_end' => '2026-12-31', 'due_date' => $dueDate]);
    $invoice->items()->create(['description' => 'Rent', 'type' => 'rent', 'quantity' => 1, 'unit_price' => 300_000_00]);
    $invoice->refresh()->issue();
    if ($status !== Invoice::STATUS_UNPAID) {
        $invoice->forceFill(['status' => $status])->save();
    }

    return $invoice->fresh();
}

it('reminds renters once, a few days before rent is due', function () {
    $soon = dueInvoice('A1', 'jean@example.test', '2026-11-29');      // in 3 days
    $later = dueInvoice('A2', 'eric@example.test', '2026-12-05');     // too far ahead
    $paid = dueInvoice('A3', 'paid@example.test', '2026-11-28', Invoice::STATUS_PAID);
    $noEmail = dueInvoice('A4', null, '2026-11-28');
    Tenancy::end();

    $this->artisan('billing:remind-due')->assertSuccessful();

    Notification::assertSentOnDemand(RentDueReminderNotification::class, fn ($n, array $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'jean@example.test' && $n->invoice->id === $soon->id);
    Notification::assertSentOnDemandTimes(RentDueReminderNotification::class, 1);

    tenancy()->initialize($this->client);
    expect($soon->fresh()?->due_reminder_sent_at)->not->toBeNull()
        ->and($later->fresh()?->due_reminder_sent_at)->toBeNull()
        ->and($paid->fresh()?->due_reminder_sent_at)->toBeNull()
        ->and($noEmail->fresh()?->due_reminder_sent_at)->toBeNull();
    Tenancy::end();

    // The next day it does not repeat.
    $this->artisan('billing:remind-due')->assertSuccessful();
    Notification::assertSentOnDemandTimes(RentDueReminderNotification::class, 1);
});

it('follows the workspace setting, including off', function () {
    expect(RentReminders::daysFor($this->client))->toBe(3);

    $this->client->update(['settings' => [...($this->client->settings ?? []), RentReminders::SETTING => 0]]);
    dueInvoice('B1', 'jean@example.test', '2026-11-27');
    Tenancy::end();

    $this->artisan('billing:remind-due')->assertSuccessful();
    Notification::assertNothingSent();
});

it('saves the reminder days from settings', function () {
    Tenancy::end();
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(WorkspaceSettings::class)
        ->assertSet('data.'.RentReminders::SETTING, 3)
        ->set('data.'.RentReminders::SETTING, 7)
        ->call('save')
        ->assertHasNoErrors();

    expect(RentReminders::daysFor($this->client->fresh()))->toBe(7);
});
