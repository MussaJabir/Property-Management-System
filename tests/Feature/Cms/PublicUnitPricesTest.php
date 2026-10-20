<?php

declare(strict_types=1);

use App\Livewire\Public\Units;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use Livewire\Livewire;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'priceco', 'name' => 'Price Co', 'status' => 'active']);

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Loc', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Lodge', 'status' => 'active']);
    Tenancy::end();
});

afterEach(fn () => Tenancy::end());

function publicUnit(Property $property, array $attributes): Unit
{
    return Unit::create(array_merge([
        'property_id' => $property->id, 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
        'rental_mode' => Unit::RENTAL_LONG_TERM,
    ], $attributes));
}

it('shows nightly rooms per night, not as 0 a month', function () {
    tenancy()->initialize($this->client);
    $room = publicUnit($this->property, ['code' => 'N1', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 80_000_00]);
    Tenancy::end();

    $this->get('/priceco/units')->assertOk()->assertSeeTextInOrder(['TZS 80,000', 'night'])->assertDontSeeText('TZS 0');
    $this->get('/priceco/units/'.$room->id)->assertOk()->assertSeeTextInOrder(['TZS 80,000', 'night'])->assertDontSeeText('month');
});

it('shows both prices for rooms let both ways, and the real rent period', function () {
    tenancy()->initialize($this->client);
    $room = publicUnit($this->property, [
        'code' => 'B1', 'rental_mode' => Unit::RENTAL_BOTH, 'nightly_rate' => 60_000_00,
        'rent_amount' => 1_800_000_00, 'billing_cycle' => Unit::BILLING_SEMI_ANNUAL,
    ]);
    Tenancy::end();

    $this->get('/priceco/units/'.$room->id)->assertOk()
        ->assertSeeTextInOrder(['TZS 60,000', 'night'])
        ->assertSeeTextInOrder(['TZS 1,800,000', '6 months']);
});

it('keeps a nightly room listed while a guest is in, but never a leased one', function () {
    tenancy()->initialize($this->client);
    publicUnit($this->property, ['code' => 'GUEST1', 'status' => Unit::STATUS_OCCUPIED, 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00]);
    $leased = publicUnit($this->property, ['code' => 'LEASED1', 'status' => Unit::STATUS_OCCUPIED, 'rental_mode' => Unit::RENTAL_BOTH, 'nightly_rate' => 50_000_00, 'rent_amount' => 300_000_00]);
    publicUnit($this->property, ['code' => 'REPAIR1', 'status' => Unit::STATUS_MAINTENANCE, 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00]);
    $renter = Renter::create(['type' => Renter::TYPE_INDIVIDUAL, 'full_name' => 'Asha', 'phone' => '0714000222']);
    Lease::create([
        'unit_id' => $leased->id, 'renter_id' => $renter->id, 'status' => Lease::STATUS_ACTIVE,
        'start_date' => now()->subMonth()->toDateString(), 'rent_amount' => 300_000_00, 'deposit_amount' => 0,
        'currency' => 'TZS', 'billing_cycle' => 'monthly', 'payment_due_day' => 1,
    ]);
    Tenancy::end();

    $this->get('/priceco/units')->assertOk()
        ->assertSeeText('GUEST1')
        ->assertDontSeeText('LEASED1')
        ->assertDontSeeText('REPAIR1');

    $this->get('/priceco/units/'.$leased->id)->assertOk()->assertSeeText('Not available right now');
});

it('compares rent filters with rent only, so nightly-only rooms do not match any budget', function () {
    tenancy()->initialize($this->client);
    publicUnit($this->property, ['code' => 'NIGHT1', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00]);
    publicUnit($this->property, ['code' => 'FLAT1', 'rent_amount' => 300_000_00]);

    Livewire::test(Units::class)
        ->set('maxRent', 400000)
        ->assertSee('FLAT1')
        ->assertDontSee('NIGHT1');
});

it('offers WhatsApp with the room already named, when the workspace has a phone', function () {
    $this->client->update(['contact_phone' => '0712 000 111']);
    tenancy()->initialize($this->client);
    $room = publicUnit($this->property, ['code' => 'W1', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00]);
    Tenancy::end();

    $this->get('/priceco/units/'.$room->id)->assertOk()
        ->assertSee('https://wa.me/255712000111?text='.rawurlencode('Hello, I am interested in W1 · Lodge. Is it free?'), false)
        ->assertSeeText('Book on WhatsApp');
});

it('hides WhatsApp buttons when the workspace has no phone', function () {
    tenancy()->initialize($this->client);
    $room = publicUnit($this->property, ['code' => 'W2', 'rent_amount' => 300_000_00]);
    Tenancy::end();

    $this->get('/priceco/units/'.$room->id)->assertOk()->assertDontSee('wa.me', false);
});

it('filters rooms by how they are let', function () {
    tenancy()->initialize($this->client);
    publicUnit($this->property, ['code' => 'NIGHTONLY', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00]);
    publicUnit($this->property, ['code' => 'MONTHONLY', 'rent_amount' => 300_000_00]);

    Livewire::test(Units::class)
        ->set('mode', 'night')->assertSee('NIGHTONLY')->assertDontSee('MONTHONLY')
        ->set('mode', 'month')->assertSee('MONTHONLY')->assertDontSee('NIGHTONLY');
});
