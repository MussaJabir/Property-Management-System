<?php

declare(strict_types=1);

use App\Filament\Operator\Actions\FrontDeskActions;
use App\Filament\Operator\Resources\Bookings\Pages\ListBookings;
use App\Filament\Operator\Resources\Ratings\Pages\ListRatings;
use App\Filament\Operator\Resources\Ratings\RatingResource;
use App\Http\Controllers\Rating\StayRatingController;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Rating;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Services\FrontDesk\ShortStayService;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'rateco', 'name' => 'Rate Lodge', 'status' => 'active']);
    $this->travelTo(Carbon::parse('2026-10-18 10:00'));

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Madale', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Lodge', 'status' => 'active']);
    $this->room = Unit::create([
        'property_id' => $this->property->id, 'code' => 'R9', 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
        'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00,
    ]);
    $this->stay = app(ShortStayService::class)->book($this->room, ['full_name' => 'Asha Bakari', 'phone' => '0712555666'], today()->subDays(2), today(), checkInNow: false);
    $this->stay->forceFill(['status' => Booking::STATUS_CHECKED_OUT, 'checked_out_at' => now()])->save();
    Tenancy::end();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('lets a guest rate their stay from the signed link, and change it', function () {
    tenancy()->initialize($this->client);
    $link = StayRatingController::linkFor($this->stay);
    Tenancy::end();

    $this->get($link)->assertOk()->assertSee('How was your stay?')->assertSee('Asha Bakari');

    $this->post($link, ['score' => 4, 'comment' => 'Clean room, friendly staff.'])->assertRedirect($link);
    $this->post($link, ['score' => 5, 'comment' => 'Even better on reflection.'])->assertRedirect($link);

    $rating = Rating::withoutGlobalScopes()->sole();
    expect($rating->score)->toBe(5)
        ->and($rating->source)->toBe(Rating::SOURCE_GUEST)
        ->and($rating->tenant_id)->toBe('rateco')
        ->and($rating->booking_id)->toBe($this->stay->id);

    $this->get($link)->assertSee('Update my rating');
});

it('refuses tampered, expired or unsigned links and bad scores', function () {
    tenancy()->initialize($this->client);
    $link = StayRatingController::linkFor($this->stay);
    Tenancy::end();

    $this->get('/rateco/rate/'.$this->stay->id)->assertForbidden();
    $this->get(str_replace('signature=', 'signature=x', $link))->assertForbidden();
    $this->post($link, ['score' => 9])->assertSessionHasErrors('score');

    $this->travel(StayRatingController::VALID_DAYS + 1)->days();
    $this->get($link)->assertForbidden();

    expect(Rating::withoutGlobalScopes()->count())->toBe(0);
});

it('lets a renter rate from the portal, one rating per lease', function () {
    tenancy()->initialize($this->client);
    $flat = Unit::create(['property_id' => $this->property->id, 'code' => 'F1', 'type' => 'apartment', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 1, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Neema Mushi', 'phone' => '0713222333']);
    $lease = Lease::create([
        'unit_id' => $flat->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE, 'start_date' => '2026-01-01',
        'rent_amount' => 1, 'deposit_amount' => 0, 'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    $user = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_RENTER, 'name' => 'Neema Mushi',
        'email' => 'neema@rate.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $renter->forceFill(['user_id' => $user->id])->save();
    Tenancy::end();

    $this->actingAs($user, 'renter')->get('/rateco/portal')->assertOk()->assertSee('How are we doing?');

    $this->post('/rateco/portal/rating', ['score' => 3, 'comment' => 'Water goes off often.'])->assertRedirect('/rateco/portal');
    $this->post('/rateco/portal/rating', ['score' => 4])->assertRedirect('/rateco/portal');

    $rating = Rating::withoutGlobalScopes()->where('lease_id', $lease->id)->sole();
    expect($rating->score)->toBe(4)->and($rating->source)->toBe(Rating::SOURCE_RENTER);

    $this->get('/rateco/portal')->assertSee('You rated us 4 out of 5');
});

it('shows staff the ratings with the average, and offers the WhatsApp link for unrated stays', function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $reception = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Rehema',
        'email' => 'rehema@rate.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $reception->assignRole('receptionist');
    $reception->forceFill(['onboarding_completed_at' => now()])->save();
    $this->actingAs($reception, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($this->client);

    $url = FrontDeskActions::whatsAppRatingUrl($this->stay->fresh());
    expect($url)->toStartWith('https://wa.me/255712555666?text=')
        ->and(urldecode($url))->toContain('Rate Lodge')->toContain('/rateco/rate/'.$this->stay->id);

    Livewire::test(ListBookings::class)->assertTableActionVisible('askForRating', $this->stay);

    Rating::create(['source' => Rating::SOURCE_GUEST, 'booking_id' => $this->stay->id, 'score' => 2, 'comment' => 'Noisy at night.']);

    expect(RatingResource::canViewAny())->toBeTrue();
    Livewire::test(ListRatings::class)
        ->assertSee('Noisy at night.')
        ->assertSee('Asha Bakari')
        ->assertSee('Average 2.0 ★ from 1 rating in the last 90 days');

    Livewire::test(ListBookings::class)->assertTableActionHidden('askForRating', $this->stay->fresh());
});
