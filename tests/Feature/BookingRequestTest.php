<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\BookingRequests\Pages\ListBookingRequests;
use App\Livewire\Public\RequestBooking;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingRequestReceivedNotification;
use App\Services\FrontDesk\ShortStayService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'lodgeco', 'name' => 'Lodge Co', 'status' => 'active', 'contact_phone' => '0712000222']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@lodge.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Sinza', 'region' => 'Dar', 'district' => 'Ubungo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Garden Lodge', 'status' => 'active']);
    $this->room = Unit::create([
        'property_id' => $property->id, 'code' => 'R4', 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 60_000_00, 'rent_amount' => 0,
        'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
    ]);
    $this->flat = Unit::create([
        'property_id' => $property->id, 'code' => 'B1', 'type' => 'apartment', 'status' => Unit::STATUS_VACANT,
        'rental_mode' => Unit::RENTAL_LONG_TERM, 'rent_amount' => 500_000_00,
        'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function requestStay(Unit $room, array $data = []): mixed
{
    return Livewire::test(RequestBooking::class, ['unit' => $room])
        ->set(array_merge([
            'check_in' => today()->addDays(3)->toDateString(),
            'check_out' => today()->addDays(6)->toDateString(),
            'guests' => 2,
            'full_name' => 'Neema Juma',
            'phone' => '0713 222 333',
        ], $data))
        ->call('submit');
}

it('shows the request form in the room page\'s booking box', function () {
    Tenancy::end();

    $this->get('/lodgeco/units/'.$this->room->id)->assertOk()
        ->assertSeeText('Request booking')
        ->assertSeeText('Book on WhatsApp');
});

it('takes a stay request without sign-in and tells the staff', function () {
    Notification::fake();

    requestStay($this->room)
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSeeText('Request sent');

    $request = BookingRequest::query()->sole();
    expect($request->kind)->toBe(BookingRequest::KIND_STAY)
        ->and($request->phone)->toBe('+255713222333')
        ->and($request->nights())->toBe(3)
        ->and($request->guests_count)->toBe(2)
        ->and($request->status)->toBe(BookingRequest::STATUS_NEW)
        ->and(Booking::query()->count())->toBe(0);

    Notification::assertSentTo($this->owner, BookingRequestReceivedNotification::class);
});

it('refuses dates that are already booked, and bad phone numbers', function () {
    app(ShortStayService::class)->book($this->room, ['full_name' => 'Peter', 'phone' => '0714000111'], today()->addDays(4), today()->addDays(5));

    requestStay($this->room)->assertHasErrors('check_in')->assertSet('sent', false);
    requestStay($this->room, ['check_in' => today()->addDays(10)->toDateString(), 'check_out' => today()->addDays(12)->toDateString(), 'phone' => '12345'])
        ->assertHasErrors('phone');

    expect(BookingRequest::query()->count())->toBe(0);
});

it('drops bot submissions quietly', function () {
    requestStay($this->room, ['website' => 'http://spam.example'])->assertSet('sent', true);

    expect(BookingRequest::query()->count())->toBe(0);
});

it('takes an enquiry with a move-in date for a room let on a lease', function () {
    Livewire::test(RequestBooking::class, ['unit' => $this->flat])
        ->assertSet('kind', BookingRequest::KIND_ENQUIRY)
        ->set(['full_name' => 'Juma', 'phone' => '0754123456', 'move_in' => today()->addWeek()->toDateString()])
        ->call('submit')
        ->assertHasNoErrors();

    $request = BookingRequest::query()->sole();
    expect($request->kind)->toBe(BookingRequest::KIND_ENQUIRY)
        ->and($request->move_in_date?->toDateString())->toBe(today()->addWeek()->toDateString())
        ->and($request->check_in_date)->toBeNull();
});

it('lets staff confirm a stay, which books the room and issues the bill', function () {
    requestStay($this->room);
    $request = BookingRequest::query()->sole();

    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ListBookingRequests::class)
        ->assertCanSeeTableRecords([$request])
        ->callAction(TestAction::make('confirm')->table($request))
        ->assertNotified('Booked');

    $booking = Booking::query()->sole();
    expect($request->fresh()->status)->toBe(BookingRequest::STATUS_CONFIRMED)
        ->and($request->fresh()->booking_id)->toBe($booking->id)
        ->and($booking->unit_id)->toBe($this->room->id)
        ->and($booking->guests_count)->toBe(2)
        ->and($booking->guest->full_name)->toBe('Neema Juma')
        ->and($booking->invoice->total_amount)->toBe(180_000_00);
});

it('keeps the request open when the room was taken meanwhile', function () {
    requestStay($this->room);
    $request = BookingRequest::query()->sole();
    app(ShortStayService::class)->book($this->room, ['full_name' => 'Walk-in', 'phone' => '0714000111'], today()->addDays(3), today()->addDays(4));

    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ListBookingRequests::class)
        ->callAction(TestAction::make('confirm')->table($request))
        ->assertNotified('Could not book');

    expect($request->fresh()->status)->toBe(BookingRequest::STATUS_NEW);
});

it('lets staff decline a stay or mark an enquiry as called back', function () {
    requestStay($this->room);
    $stay = BookingRequest::query()->sole();
    Livewire::test(RequestBooking::class, ['unit' => $this->flat])->set(['full_name' => 'Juma', 'phone' => '0754123456'])->call('submit');
    $enquiry = BookingRequest::query()->where('kind', BookingRequest::KIND_ENQUIRY)->sole();

    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ListBookingRequests::class)
        ->assertActionHidden(TestAction::make('confirm')->table($enquiry))
        ->callAction(TestAction::make('decline')->table($stay), ['reason' => 'Full that week'])
        ->callAction(TestAction::make('handled')->table($enquiry));

    expect($stay->fresh()->status)->toBe(BookingRequest::STATUS_DECLINED)
        ->and($stay->fresh()->decline_reason)->toBe('Full that week')
        ->and($enquiry->fresh()->status)->toBe(BookingRequest::STATUS_HANDLED);
});

it('never shows another workspace\'s requests', function () {
    requestStay($this->room);
    Tenancy::end();

    $other = Client::create(['slug' => 'otherlodge', 'name' => 'Other Lodge', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($other->id);
    $theirOwner = User::create([
        'tenant_id' => $other->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Baraka',
        'email' => 'baraka@other.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $theirOwner->assignRole('owner');
    $theirOwner->forceFill(['onboarding_completed_at' => now()])->save();

    $this->actingAs($theirOwner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($other);

    Livewire::test(ListBookingRequests::class)->assertCountTableRecords(0);
});
