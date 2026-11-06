<?php

declare(strict_types=1);

use App\Livewire\Marketplace\Browse;
use App\Models\Client;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Marketplace\ListingSearch;
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
