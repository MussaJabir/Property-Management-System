<?php

declare(strict_types=1);

use App\Livewire\Marketplace\Browse;
use App\Livewire\Public\RequestBooking;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Marketplace\ListingSearch;
use App\Support\RoomHighlights;
use Database\Seeders\PlanSeeder;
use Livewire\Livewire;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    $this->pro = Plan::query()->where('slug', 'pro')->firstOrFail();
});

afterEach(fn () => Tenancy::end());

/**
 * A landlord with one property and the given units, made inside its own
 * workspace, then tenancy ended so the test reads like a visitor.
 *
 * @param  array<string, mixed>  $clientAttributes
 * @param  list<array<string, mixed>>  $units
 * @param  array<string, string>  $location
 * @return list<Unit>
 */
function roomLandlord(string $slug, array $clientAttributes, array $units, array $location = ['name' => 'Njiro', 'district' => 'Arusha City', 'region' => 'Arusha']): array
{
    $client = Client::create(['slug' => $slug, 'name' => ucfirst($slug).' Homes', 'status' => 'active', 'contact_phone' => '+255712000111', ...$clientAttributes]);
    tenancy()->initialize($client);

    $property = Property::create([
        'location_id' => Location::create($location)->id,
        'name' => ucfirst($slug).' Court', 'status' => 'active',
    ]);

    $created = array_map(fn (array $unit): Unit => Unit::create([
        'property_id' => $property->id, 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
        'on_marketplace' => true, ...$unit,
    ]), $units);

    Tenancy::end();

    return $created;
}

it('opens a listed room inside the marketplace with the landlord and the booking form', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101', 'description' => 'Quiet room near the market.'], ['code' => 'R102']]);

    $this->get('/browse/njiro/'.$unit->id)
        ->assertOk()
        ->assertSee('R101 · Njiro Court')
        ->assertSee('Quiet room near the market.')
        ->assertSee('Listed by Njiro Homes')
        ->assertSee('2 places listed')
        ->assertSee('Back to results')
        ->assertSee('/browse?landlord=njiro', false)
        ->assertSee('<meta property="og:title" content="R101 · Njiro Court · PMS">', false)
        ->assertSee('<meta property="og:url" content="'.route('marketplace.room', ['tenant' => 'njiro', 'unit' => $unit->id]).'">', false)
        ->assertSeeLivewire('public.request-booking');
});

it('links back to the same search', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']]);

    $this->get('/browse/njiro/'.$unit->id.'?q=Arusha&max=400000&amenities[]=wifi&amenities[]=bogus&evil=1')
        ->assertOk()
        ->assertSee(route('marketplace.browse', ['q' => 'Arusha', 'max' => '400000', 'amenities' => ['wifi']]))
        ->assertDontSee('evil=1', false)
        ->assertDontSee('bogus', false);
});

it('sends rooms that are not on the marketplace to the landlord page', function (array $client, array $unit) {
    $attributes = $client + ['plan_id' => $this->pro->id];
    [$room] = roomLandlord('njiro', $attributes, [['code' => 'R101', ...$unit]]);

    $this->get('/browse/njiro/'.$room->id)->assertRedirect('/njiro/units/'.$room->id);
})->with([
    'switched off by the landlord' => [[], ['on_marketplace' => false]],
    'a demo workspace' => [['is_demo' => true], []],
    'a plan without the marketplace' => [['plan_id' => null], []],
]);

it('cannot open another landlord room under the wrong workspace', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']]);
    roomLandlord('other', ['plan_id' => $this->pro->id], [['code' => 'X1']]);

    $this->get('/browse/other/'.$unit->id)->assertNotFound();
});

it('shows more places in the same region from every landlord, never demo ones', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101'], ['code' => 'R102'], ['code' => 'HIDDEN', 'on_marketplace' => false]]);
    roomLandlord('kijenge', ['plan_id' => $this->pro->id], [['code' => 'K7']], ['name' => 'Kijenge', 'district' => 'Arusha City', 'region' => 'Arusha']);
    roomLandlord('sinza', ['plan_id' => $this->pro->id], [['code' => 'DSM1']], ['name' => 'Sinza', 'district' => 'Ubungo', 'region' => 'Dar es Salaam']);
    roomLandlord('demo-ws', ['is_demo' => true], [['code' => 'DEMO1']]);

    $this->get('/browse/njiro/'.$unit->id)
        ->assertOk()
        ->assertSee('More places in Arusha')
        ->assertSee('R102 · Njiro Court')
        ->assertSee('K7 · Kijenge Court')
        ->assertSee('Listed by Kijenge Homes')
        ->assertDontSee('HIDDEN')
        ->assertDontSee('DSM1')
        ->assertDontSee('DEMO1');
});

it('shows one landlord places on /browse', function () {
    roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']]);
    roomLandlord('kijenge', ['plan_id' => $this->pro->id], [['code' => 'K7']]);

    $codes = app(ListingSearch::class)->search(['landlord' => 'njiro'])->getCollection()->pluck('code')->all();
    expect($codes)->toBe(['R101']);

    Livewire::withQueryParams(['landlord' => 'njiro'])
        ->test(Browse::class)
        ->assertSee('Places by Njiro Homes')
        ->assertSee('R101 · Njiro Court')
        ->assertDontSee('K7 · Kijenge Court')
        ->call('clearFilters')
        ->assertSee('K7 · Kijenge Court');
});

it('opens rooms from /browse cards carrying the search', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']]);

    Livewire::test(Browse::class)
        ->set('search', 'Njiro')
        ->assertSee('/browse/njiro/'.$unit->id.'?q=Njiro', false);
});

it('keeps the landlord own room page working', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101', 'amenities' => ['wifi', 'parking']]]);

    $this->get('/njiro/units/'.$unit->id)
        ->assertOk()
        ->assertSee('R101 · Njiro Court')
        ->assertSee('What this room has')
        ->assertSee('All rooms')
        ->assertSeeLivewire('public.request-booking');
});

it('offers landlords a way in from the marketplace', function () {
    $this->get('/browse')
        ->assertOk()
        ->assertSee('Landlord login')
        ->assertSee(route('filament.operator.auth.login'), false)
        ->assertDontSee('My dashboard');
});

it('takes a signed-in landlord straight to their dashboard', function () {
    $client = Client::create(['slug' => 'dash', 'name' => 'Dash Homes', 'status' => 'active', 'plan_id' => $this->pro->id]);
    $owner = User::create([
        'tenant_id' => $client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Neema',
        'email' => 'neema@dash.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);

    $this->actingAs($owner, 'web')
        ->get('/browse')
        ->assertOk()
        ->assertSee('My dashboard')
        ->assertSee(route('filament.operator.pages.dashboard'), false)
        ->assertDontSee('Landlord login');
});

it('groups the phone home screen into rows by area, busiest first, featured first', function () {
    roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'A1'], ['code' => 'A2', 'featured_until' => now()->addDays(3)], ['code' => 'A3']]);
    roomLandlord('sinza', ['plan_id' => $this->pro->id], [['code' => 'D1']], ['name' => 'Sinza', 'district' => 'Ubungo', 'region' => 'Dar es Salaam']);

    $rows = app(ListingSearch::class)->rowsByRegion();

    expect(array_column($rows, 'region'))->toBe(['Arusha', 'Dar es Salaam'])
        ->and(array_column($rows, 'total'))->toBe([3, 1])
        ->and($rows[0]['units']->first()->code)->toBe('A2');
});

it('filters by kind of place, and short stays from the chip', function () {
    roomLandlord('mix', ['plan_id' => $this->pro->id], [
        ['code' => 'ROOM1'],
        ['code' => 'APT1', 'type' => Unit::TYPE_APARTMENT],
        ['code' => 'SHOP1', 'type' => Unit::TYPE_SHOP],
        ['code' => 'NIGHT1', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00, 'rent_amount' => 0],
    ]);
    $codes = fn (array $filters) => app(ListingSearch::class)->search($filters)->getCollection()->pluck('code')->sort()->values()->all();

    expect($codes(['type' => 'apartments']))->toBe(['APT1'])
        ->and($codes(['type' => 'business']))->toBe(['SHOP1'])
        ->and($codes(['type' => 'rooms']))->toBe(['NIGHT1', 'ROOM1'])
        ->and($codes(['type' => 'nonsense']))->toHaveCount(4);

    Livewire::test(Browse::class)
        ->call('pickType', 'night')
        ->assertSet('stay', 'night')
        ->assertSet('type', '')
        ->call('pickType', 'apartments')
        ->assertSet('type', 'apartments')
        ->assertSet('stay', '');
});

it('shows rows on the phone home screen and a grid once searching', function () {
    roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'A1']]);

    Livewire::test(Browse::class)
        ->assertSee('Where do you want to live?')
        ->assertSee('Places in Arusha')
        ->assertSee('Show 1 place')
        ->set('search', 'Njiro')
        ->assertDontSee('Places in Arusha')
        ->assertSee('/browse/njiro/', false);
});

it('builds the phone highlights from the room data', function () {
    [$monthly, $bare, $nightly] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [
        ['code' => 'M1', 'amenities' => ['parking', 'wifi', 'water_247', 'security']],
        ['code' => 'B1', 'amenities' => []],
        ['code' => 'N1', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00, 'rent_amount' => 0],
    ]);
    tenancy()->initialize(Client::find('njiro'));

    $rows = RoomHighlights::for($monthly->fresh()->load('property.location'));
    expect(array_column($rows, 'title'))->toBe(['In Njiro, Arusha City', '24/7 water · Security · Wi-Fi / Internet', 'Monthly rent, no broker fees'])
        ->and($rows[1]['icon'])->toBe('amenity:water_247');

    expect(RoomHighlights::for($bare->fresh()->load('property.location')))->toHaveCount(2)
        ->and(RoomHighlights::for($nightly->fresh()->load('property.location'))[1]['title'])->toBe('By the night, no broker fees');
});

it('lists areas with how many places and the cheapest TZS price', function () {
    roomLandlord('njiro', ['plan_id' => $this->pro->id], [
        ['code' => 'A1', 'rent_amount' => 300_000_00],
        ['code' => 'A2', 'rent_amount' => 1_200_000_00, 'billing_cycle' => 'semi_annual'],
        ['code' => 'A3', 'rent_amount' => 500_00, 'rent_currency' => 'USD'],
    ]);
    roomLandlord('sinza', ['plan_id' => $this->pro->id], [
        ['code' => 'D1', 'rent_amount' => 600_00, 'rent_currency' => 'USD'],
    ], ['name' => 'Sinza', 'district' => 'Ubungo', 'region' => 'Dar es Salaam']);
    roomLandlord('stays', ['plan_id' => $this->pro->id], [
        ['code' => 'S1', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 45_000_00, 'rent_amount' => 0],
    ], ['name' => 'Kisasa', 'district' => 'Dodoma City', 'region' => 'Dodoma']);

    expect(app(ListingSearch::class)->regionSummaries())->toBe([
        ['region' => 'Arusha', 'total' => 3, 'from' => 200_000, 'per' => 'month'],
        ['region' => 'Dar es Salaam', 'total' => 1, 'from' => null, 'per' => null],
        ['region' => 'Dodoma', 'total' => 1, 'from' => 45_000, 'per' => 'night'],
    ]);
});

it('fills the move-in date from the quick buttons and sends it to the landlord', function () {
    $this->travelTo(now()->setDate(2026, 11, 7));
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']]);
    tenancy()->initialize(Client::find('njiro'));

    Livewire::test(RequestBooking::class, ['unit' => $unit])
        ->call('pickMoveIn', 'asap')->assertSet('move_in', '2026-11-07')
        ->call('pickMoveIn', 'next_month')->assertSet('move_in', '2026-12-01')
        ->call('pickMoveIn', 'date')->assertSet('moveInChoice', 'date')->assertSee('Move-in date')
        ->set('full_name', 'Neema Juma')->set('phone', '0712345678')
        ->call('submit')
        ->assertSet('sent', true);

    expect(BookingRequest::query()->latest('created_at')->first()->move_in_date->toDateString())->toBe('2026-12-01');
});

it('shows the phone layout parts on the room page', function () {
    $many = ['air_conditioning', 'wifi', 'parking', 'water_247', 'backup_power', 'security', 'furnished', 'cctv'];
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101', 'amenities' => $many]]);

    $this->get('/browse/njiro/'.$unit->id)
        ->assertOk()
        ->assertSee('In Njiro, Arusha City')
        ->assertSee('Show all 8')
        ->assertSee('Ask about this place')
        ->assertSee('As soon as possible');
});

it('does not repeat the property name when the area already has it', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']], ['name' => 'Njiro Court area', 'district' => 'Arusha City', 'region' => 'Arusha']);

    $this->get('/browse/njiro/'.$unit->id)
        ->assertOk()
        ->assertDontSee('Njiro Court · Njiro Court area')
        ->assertSee('Njiro Court area, Arusha City, Arusha.');
});

it('names the property and the area when they differ', function () {
    [$unit] = roomLandlord('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']]);

    $this->get('/browse/njiro/'.$unit->id)->assertSee('Njiro Court · Njiro, Arusha City, Arusha.');
});
