<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\Dashboard;
use App\Filament\Operator\Pages\FrontDesk;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Filament\Operator\Resources\Bookings\Pages\ListBookings;
use App\Filament\Operator\Resources\Guests\GuestResource;
use App\Filament\Operator\Resources\Units\Pages\EditUnit;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\FrontDesk\ShortStayService;
use Database\Seeders\PlanSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'deskco', 'name' => 'Desk Co', 'status' => 'active']);
    $this->travelTo(Carbon::parse('2026-10-17 15:00'));

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->receptionist = deskUser($this->client, 'receptionist', 'reception@desk.test');

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Madale', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Mshale Lodge', 'status' => 'active']);
    $this->room = Unit::create([
        'property_id' => $this->property->id, 'code' => 'R1', 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
        'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 80_000_00,
    ]);

    $this->actingAs($this->receptionist, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function deskUser(Client $client, string $role, string $email): User
{
    $user = User::create([
        'tenant_id' => $client->id, 'type' => User::TYPE_OPERATOR, 'name' => ucfirst($role),
        'email' => $email, 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $user->assignRole($role);
    $user->forceFill(['onboarding_completed_at' => now()])->save();

    return $user;
}

it('checks a walk-in in from a free room in one modal, taking payment at the same time', function () {
    Livewire::test(FrontDesk::class)
        ->assertSee('R1')
        ->assertSee('Free')
        ->callAction(TestAction::make('roomCheckIn')->arguments(['unit' => $this->room->id]), [
            'full_name' => 'Grace Mollel', 'phone' => '0754123456', 'nights' => 2, 'nightly_rate' => 80_000,
            'amount' => 100_000, 'channel' => Payment::PROVIDER_MPESA, 'reference' => 'QJK7X2',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Grace Mollel checked in to R1')
        ->assertSee('In house');

    $booking = Booking::query()->sole();
    $invoice = $booking->invoice;

    expect($booking->status)->toBe(Booking::STATUS_CHECKED_IN)
        ->and($booking->check_out_date->toDateString())->toBe('2026-10-19')
        ->and($invoice?->total_amount)->toBe(160_000_00)
        ->and($invoice?->amount_paid)->toBe(100_000_00)
        ->and($invoice?->status)->toBe(Invoice::STATUS_PARTIAL)
        ->and(Payment::query()->value('mobile_money_provider'))->toBe(Payment::PROVIDER_MPESA);
});

it('takes a booking, then checks the guest in on arrival and out with the balance', function () {
    Livewire::test(ListBookings::class)
        ->callAction('book', [
            'check_in_date' => '2026-10-18', 'check_out_date' => '2026-10-20', 'unit_id' => $this->room->id,
            'full_name' => 'Juma Said', 'phone' => '0713000111', 'nightly_rate' => 75_000,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Booked R1 for Juma Said');

    $booking = Booking::query()->sole();
    expect($booking->status)->toBe(Booking::STATUS_RESERVED)
        ->and($booking->invoice?->total_amount)->toBe(150_000_00);

    // Not yet: arrives tomorrow.
    Livewire::test(ListBookings::class)->assertTableActionHidden('checkIn', $booking);

    $this->travelTo(Carbon::parse('2026-10-18 13:00'));
    Livewire::test(FrontDesk::class)
        ->assertSee('Arriving today')
        ->callAction(TestAction::make('arrivalCheckIn')->arguments(['booking' => $booking->id]))
        ->assertNotified('Juma Said checked in');

    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    Livewire::test(FrontDesk::class)
        ->mountAction(TestAction::make('stayCheckOut')->arguments(['booking' => $booking->id]))
        ->assertSchemaStateSet(['amount' => 150_000], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertNotified('Juma Said checked out');

    expect($booking->fresh()?->status)->toBe(Booking::STATUS_CHECKED_OUT)
        ->and($booking->invoice?->fresh()?->status)->toBe(Invoice::STATUS_PAID)
        ->and($this->room->fresh()?->status)->toBe(Unit::STATUS_VACANT);
});

it('extends a stay from the board and refuses a room that is taken', function () {
    $stays = app(ShortStayService::class);
    $booking = $stays->book($this->room, ['full_name' => 'Grace', 'phone' => '0754123456'], today(), today()->addDay(), checkInNow: true);
    $stays->book($this->room, ['full_name' => 'Next', 'phone' => '0713000111'], today()->addDays(3), today()->addDays(4));

    Livewire::test(FrontDesk::class)
        ->callAction(TestAction::make('stayExtend')->arguments(['booking' => $booking->id]), ['extra_nights' => 1])
        ->assertNotified('Stay extended to 19/10/2026')
        // Two more nights would run into the next guest.
        ->callAction(TestAction::make('stayExtend')->arguments(['booking' => $booking->id]), ['extra_nights' => 2])
        ->assertNotified('R1 is already booked for some of those nights.');

    expect($booking->fresh()?->check_out_date->toDateString())->toBe('2026-10-19');
});

it('keeps the front desk to plans with short stays and to staff with front desk access', function () {
    expect(FrontDesk::canAccess())->toBeTrue()
        ->and(BookingResource::canCreate())->toBeTrue()
        ->and(GuestResource::canAccess())->toBeTrue();

    $this->seed(PlanSeeder::class);
    $this->client->forceFill(['plan_id' => Plan::query()->where('slug', 'starter')->value('id')])->save();
    $this->receptionist->refresh();
    expect(FrontDesk::canAccess())->toBeFalse()
        ->and(BookingResource::canAccess())->toBeFalse();

    $this->client->forceFill(['plan_id' => Plan::query()->where('slug', 'pro')->value('id')])->save();
    $this->receptionist->refresh();
    $fundi = deskUser($this->client, 'maintenance-staff', 'fundi@desk.test');
    $this->actingAs($fundi, 'web');
    expect(FrontDesk::canAccess())->toBeFalse();
});

it('sets a unit up for short stays from the unit form', function () {
    $owner = deskUser($this->client, 'owner', 'owner@desk.test');
    $this->actingAs($owner, 'web');
    $flat = Unit::create(['property_id' => $this->property->id, 'code' => 'F1', 'type' => 'apartment', 'status' => Unit::STATUS_VACANT, 'rent_amount' => 500_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);

    Livewire::test(EditUnit::class, ['record' => $flat->id])
        ->assertFormFieldVisible('rental_mode')
        ->assertFormFieldHidden('nightly_rate')
        ->fillForm(['rental_mode' => Unit::RENTAL_BOTH, 'nightly_rate' => 120_000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($flat->fresh()?->rental_mode)->toBe(Unit::RENTAL_BOTH)
        ->and($flat->fresh()?->nightly_rate)->toBe(120_000_00)
        ->and($flat->fresh()?->rent_amount)->toBe(500_000_00);
});

it('offers Check in on Today only where rooms take short stays', function () {
    Livewire::test(Dashboard::class)->assertActionVisible('checkInGuest');

    $this->room->update(['rental_mode' => Unit::RENTAL_LONG_TERM]);
    Livewire::test(Dashboard::class)->assertActionHidden('checkInGuest');
});
