<?php

declare(strict_types=1);

use App\Filament\Operator\Resources\Leases\Pages\ListLeases;
use App\Filament\Operator\Resources\Ratings\Pages\ListRatings;
use App\Http\Controllers\Rating\LeaseRatingController;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Rating;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'reviewco', 'name' => 'Review Co', 'status' => 'active']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Zuhura',
        'email' => 'zuhura@review.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Mbezi', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Palm Court', 'status' => 'active']);
    $this->room = Unit::create([
        'property_id' => $property->id, 'code' => 'P1', 'type' => 'apartment', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 400_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
    ]);
    $this->renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Grace Mollel', 'phone' => '0754123456']);
    $this->lease = Lease::create([
        'unit_id' => $this->room->id, 'renter_id' => $this->renter->id, 'status' => Lease::STATUS_ENDED,
        'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->subMonth()->toDateString(),
        'rent_amount' => 400_000_00, 'deposit_amount' => 0, 'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    $this->rating = Rating::create(['source' => Rating::SOURCE_RENTER, 'lease_id' => $this->lease->id, 'score' => 4, 'comment' => 'Quiet and clean, water every day.']);
    Tenancy::end();
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function asOwner(object $test): void
{
    $test->actingAs($test->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
    tenancy()->initialize($test->client);
}

it('keeps a review off the website until the owner approves it', function () {
    $this->get('/reviewco/units/'.$this->room->id)->assertOk()
        ->assertDontSeeText('Quiet and clean');

    asOwner($this);
    Livewire::test(ListRatings::class)
        ->assertSee('Hidden')
        ->callAction(TestAction::make('publish')->table($this->rating))
        ->assertNotified('Shown on your website');
    Tenancy::end();

    $this->get('/reviewco/units/'.$this->room->id)->assertOk()
        ->assertSeeText('Quiet and clean, water every day.')
        ->assertSeeText('Grace')
        ->assertDontSeeText('Mollel')
        ->assertSeeText('4.0')
        ->assertSeeText('1 review');

    $this->get('/reviewco/units')->assertOk()->assertSeeText('4.0');
});

it('hides a review again when the renter changes it', function () {
    tenancy()->initialize($this->client);
    $this->rating->forceFill(['published_at' => now()])->save();

    $this->rating->update(['score' => 1, 'comment' => 'Changed my mind.']);

    expect($this->rating->fresh()->published_at)->toBeNull();
});

it('lets the owner hide a review that is on the website', function () {
    tenancy()->initialize($this->client);
    $this->rating->forceFill(['published_at' => now()])->save();

    asOwner($this);
    Livewire::test(ListRatings::class)->callAction(TestAction::make('unpublish')->table($this->rating));

    expect($this->rating->fresh()->published_at)->toBeNull();
});

it('lets a renter review their room from a signed link, without signing in', function () {
    tenancy()->initialize($this->client);
    $this->rating->delete();
    $link = LeaseRatingController::linkFor($this->lease);
    Tenancy::end();

    $this->get($link)->assertOk()->assertSeeText('How is your room?')->assertSeeText('Grace Mollel');
    $this->post($link, ['score' => 5, 'comment' => 'Best landlord in Mbezi.'])->assertRedirect($link);

    tenancy()->initialize($this->client);
    $rating = Rating::query()->sole();
    expect($rating->source)->toBe(Rating::SOURCE_RENTER)
        ->and($rating->lease_id)->toBe($this->lease->id)
        ->and($rating->score)->toBe(5)
        ->and($rating->published_at)->toBeNull();
    Tenancy::end();

    $this->get('/reviewco/rate/lease/'.$this->lease->id)->assertForbidden();
});

it('offers staff a WhatsApp review link on active leases', function () {
    tenancy()->initialize($this->client);
    $this->lease->forceFill(['status' => Lease::STATUS_ACTIVE, 'end_date' => null])->save();

    asOwner($this);
    Livewire::test(ListLeases::class)
        ->assertActionVisible(TestAction::make('askForReview')->table($this->lease))
        ->assertActionHasUrl(TestAction::make('askForReview')->table($this->lease), LeaseRatingController::whatsAppUrl($this->lease));

    expect(LeaseRatingController::whatsAppUrl($this->lease))->toStartWith('https://wa.me/255754123456?text=');
});
