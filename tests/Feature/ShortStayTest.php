<?php

declare(strict_types=1);

use App\Authorization\OperatorPermissions;
use App\Exceptions\RoomNotAvailable;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Services\FrontDesk\ShortStayService;
use App\Services\Plans\PlanUsage;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'staysco', 'name' => 'Stays Co', 'status' => 'active']);
    $this->travelTo(Carbon::parse('2026-10-17 15:00'));
    tenancy()->initialize($this->client);

    $location = Location::create(['name' => 'Madale', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Mshale Lodge', 'status' => 'active']);
    $this->room = stayUnit($this->property, 'R1');
    $this->stays = app(ShortStayService::class);
});

afterEach(fn () => Tenancy::end());

function stayUnit(Property $property, string $code, string $mode = Unit::RENTAL_SHORT_STAY): Unit
{
    return Unit::create([
        'property_id' => $property->id, 'code' => $code, 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
        'rental_mode' => $mode, 'nightly_rate' => 80_000_00,
    ]);
}

function guestData(string $name = 'Grace Mollel', string $phone = '0754123456'): array
{
    return ['full_name' => $name, 'phone' => $phone];
}

it('checks a walk-in guest in on one call: guest, booking, issued invoice, room occupied', function () {
    $booking = $this->stays->book($this->room, guestData(), today(), today()->addDays(3), checkInNow: true);

    $invoice = $booking->invoice;
    expect($booking->status)->toBe(Booking::STATUS_CHECKED_IN)
        ->and($booking->nights())->toBe(3)
        ->and((string) $booking->guest?->phone)->toBe('+255754123456')
        ->and($this->room->fresh()?->status)->toBe(Unit::STATUS_OCCUPIED)
        ->and($invoice?->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice?->invoice_number)->not->toBeEmpty()
        ->and($invoice?->total_amount)->toBe(240_000_00)
        ->and($invoice?->due_date?->toDateString())->toBe('2026-10-17')
        ->and($invoice?->bill_to_name)->toBe('Grace Mollel')
        ->and($invoice?->unit_code)->toBe('R1')
        ->and($invoice?->lease_id)->toBeNull();
});

it('refuses overlapping nights but allows a stay starting on the previous check-out day', function () {
    $this->stays->book($this->room, guestData(), today(), today()->addDays(2));

    expect(fn () => $this->stays->book($this->room, guestData('Juma', '0713000111'), today()->addDay(), today()->addDays(4)))
        ->toThrow(RoomNotAvailable::class);

    $next = $this->stays->book($this->room, guestData('Juma', '0713000111'), today()->addDays(2), today()->addDays(4));
    expect($next->status)->toBe(Booking::STATUS_RESERVED);
});

it('has the database refuse a double booking even when the app check is skipped', function () {
    $guest = Guest::create(guestData());
    $row = ['unit_id' => $this->room->id, 'guest_id' => $guest->id, 'nightly_rate' => 1, 'currency' => 'TZS', 'status' => Booking::STATUS_RESERVED];

    Booking::create($row + ['check_in_date' => '2026-10-20', 'check_out_date' => '2026-10-23']);

    // Own savepoint, so the refused insert doesn't abort the test transaction.
    expect(fn () => DB::transaction(fn () => Booking::create($row + ['check_in_date' => '2026-10-22', 'check_out_date' => '2026-10-24'])))
        ->toThrow(QueryException::class, 'bookings_no_overlap');

    // Cancelled bookings don't hold the room.
    Booking::query()->update(['status' => Booking::STATUS_CANCELLED]);
    Booking::create($row + ['check_in_date' => '2026-10-22', 'check_out_date' => '2026-10-24']);
    expect(Booking::query()->live()->count())->toBe(1);
});

it('frees the nights and cancels the unpaid bill when a reservation is cancelled', function () {
    $booking = $this->stays->book($this->room, guestData(), today()->addDay(), today()->addDays(3));
    $invoice = $booking->invoice;

    $booking->cancel();

    expect($booking->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($invoice?->fresh()?->status)->toBe(Invoice::STATUS_CANCELLED)
        ->and($this->stays->isAvailable($this->room, today()->addDay(), today()->addDays(3)))->toBeTrue();
});

it('extends a stay: later check-out, extra nights on the same bill, paid bill reopens', function () {
    $booking = $this->stays->book($this->room, guestData(), today(), today()->addDays(2), checkInNow: true);
    $invoice = $booking->invoice;
    Payment::create([
        'invoice_id' => $invoice?->id, 'amount' => 160_000_00, 'currency' => 'TZS',
        'payment_date' => today(), 'method' => Payment::METHOD_CASH, 'status' => Payment::STATUS_COMPLETED,
    ]);
    expect($invoice?->fresh()?->status)->toBe(Invoice::STATUS_PAID);

    $this->stays->extend($booking, today()->addDays(4));

    $invoice = $invoice?->fresh();
    expect($booking->fresh()?->check_out_date->toDateString())->toBe('2026-10-21')
        ->and($invoice?->total_amount)->toBe(320_000_00)
        ->and($invoice?->balanceDue())->toBe(160_000_00)
        ->and($invoice?->status)->toBe(Invoice::STATUS_PARTIAL)
        ->and($invoice?->billing_period_end?->toDateString())->toBe('2026-10-21');
});

it('will not extend into the next guest\'s booking', function () {
    $booking = $this->stays->book($this->room, guestData(), today(), today()->addDays(2), checkInNow: true);
    $this->stays->book($this->room, guestData('Juma', '0713000111'), today()->addDays(3), today()->addDays(5));

    expect(fn () => $this->stays->extend($booking, today()->addDays(4)))->toThrow(RoomNotAvailable::class)
        ->and($booking->fresh()?->check_out_date->toDateString())->toBe('2026-10-19');
});

it('checks out and frees the room, but only checks in from the first night', function () {
    $later = $this->stays->book($this->room, guestData(), today()->addDays(5), today()->addDays(6));
    expect($later->canCheckIn())->toBeFalse();

    $booking = $this->stays->book($this->room, guestData('Juma', '0713000111'), today(), today()->addDay());
    $booking->checkIn();
    expect($this->room->fresh()?->status)->toBe(Unit::STATUS_OCCUPIED);

    $booking->checkOut();
    expect($booking->status)->toBe(Booking::STATUS_CHECKED_OUT)
        ->and($booking->checked_out_at)->not->toBeNull()
        ->and($this->room->fresh()?->status)->toBe(Unit::STATUS_VACANT);
});

it('only books short-stay units that are not let on a lease', function () {
    $flat = stayUnit($this->property, 'F1', Unit::RENTAL_LONG_TERM);
    expect(fn () => $this->stays->book($flat, guestData(), today(), today()->addDay()))->toThrow(RoomNotAvailable::class);

    $both = stayUnit($this->property, 'B1', Unit::RENTAL_BOTH);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Long Stayer', 'phone' => '0715000222']);
    Lease::create([
        'unit_id' => $both->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE, 'start_date' => '2026-01-01',
        'rent_amount' => 1, 'deposit_amount' => 0, 'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    expect(fn () => $this->stays->book($both, guestData(), today(), today()->addDay()))->toThrow(RoomNotAvailable::class);
});

it('recognises a returning guest by phone number', function () {
    $this->stays->book($this->room, guestData('Grace Mollel', '0754123456'), today(), today()->addDay());
    $this->stays->book($this->room, guestData('Grace M. Mollel', '+255754123456'), today()->addDays(3), today()->addDays(4));

    expect(Guest::query()->count())->toBe(1)
        ->and(Guest::query()->value('full_name'))->toBe('Grace M. Mollel')
        ->and(Invoice::query()->billToMatches('Mollel')->count())->toBe(2);
});

it('sells short stays on Pro and Enterprise and gives receptionists the front desk', function () {
    $this->seed(PlanSeeder::class);

    foreach (['basic' => false, 'starter' => false, 'pro' => true, 'enterprise' => true] as $slug => $has) {
        $this->client->forceFill(['plan_id' => Plan::query()->where('slug', $slug)->value('id')])->save();
        expect(PlanUsage::for($this->client->fresh())->hasFeature('short_stays'))->toBe($has);
    }

    expect(OperatorPermissions::forRole('receptionist'))->toContain('frontdesk.manage', 'billing.manage')
        ->and(OperatorPermissions::forRole('accountant'))->toContain('frontdesk.view')
        ->and(OperatorPermissions::forRole('accountant'))->not->toContain('frontdesk.manage');
});
