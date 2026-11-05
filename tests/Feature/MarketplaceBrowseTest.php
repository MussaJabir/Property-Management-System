<?php

declare(strict_types=1);

use App\Livewire\Marketplace\Browse;
use App\Models\Client;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\SystemSetting;
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
 * A client with one property and the given units, created inside its own
 * workspace, then tenancy ended so the test reads like a public visitor.
 *
 * @param  array<string, mixed>  $clientAttributes
 * @param  list<array<string, mixed>>  $units
 * @return list<Unit>
 */
function landlordWithUnits(string $slug, array $clientAttributes, array $units, array $location = ['name' => 'Njiro', 'district' => 'Arusha City', 'region' => 'Arusha']): array
{
    $client = Client::create(['slug' => $slug, 'name' => ucfirst($slug).' Homes', 'status' => 'active', ...$clientAttributes]);
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

function listedCodes(array $filters = []): array
{
    return app(ListingSearch::class)->search($filters)->getCollection()->pluck('code')->all();
}

it('lists opted-in vacant units from active clients on a plan with the marketplace', function () {
    landlordWithUnits('njiro', ['plan_id' => $this->pro->id], [
        ['code' => 'LISTED'],
        ['code' => 'NOT-OPTED', 'on_marketplace' => false],
        ['code' => 'OCCUPIED', 'status' => Unit::STATUS_OCCUPIED],
    ]);

    expect(listedCodes())->toBe(['LISTED']);
});

it('leaves out clients that are not active or whose plan lacks the marketplace', function () {
    $legacy = Plan::create(['name' => 'Legacy', 'slug' => 'legacy', 'price_tzs' => 0, 'billing_period' => 'monthly', 'features' => [], 'is_public' => false]);

    landlordWithUnits('suspended', ['plan_id' => $this->pro->id, 'status' => 'suspended'], [['code' => 'S1']]);
    landlordWithUnits('trialling', ['plan_id' => $this->pro->id, 'status' => 'trial'], [['code' => 'T1']]);
    landlordWithUnits('legacy', ['plan_id' => $legacy->id], [['code' => 'L1']]);
    landlordWithUnits('noplan', [], [['code' => 'N1']]);
    landlordWithUnits('paying', ['plan_id' => $this->pro->id], [['code' => 'P1']]);

    expect(listedCodes())->toBe(['P1']);
});

it('shows featured units first, and demo workspaces last', function () {
    landlordWithUnits('demo-ws', ['is_demo' => true], [['code' => 'DEMO']]);
    landlordWithUnits('real', ['plan_id' => $this->pro->id], [
        ['code' => 'PLAIN'],
        ['code' => 'BOOSTED', 'featured_until' => now()->addDays(7)],
        ['code' => 'EXPIRED', 'featured_until' => now()->subDay()],
    ]);

    $codes = listedCodes();

    expect($codes[0])->toBe('BOOSTED')
        ->and(end($codes))->toBe('DEMO')
        ->and($codes)->toHaveCount(4);
});

it('hides every demo listing when the setting is on', function () {
    landlordWithUnits('demo-ws', ['is_demo' => true], [['code' => 'DEMO']]);
    landlordWithUnits('real', ['plan_id' => $this->pro->id], [['code' => 'REAL']]);

    SystemSetting::put(ListingSearch::HIDE_DEMO_SETTING, true);

    expect(listedCodes())->toBe(['REAL']);
});

it('finds units by area across clients', function () {
    landlordWithUnits('arusha', ['plan_id' => $this->pro->id], [['code' => 'ARU']]);
    landlordWithUnits('sinza', ['plan_id' => $this->pro->id], [['code' => 'DSM']], ['name' => 'Sinza', 'district' => 'Ubungo', 'region' => 'Dar es Salaam']);

    expect(listedCodes(['q' => 'arusha']))->toBe(['ARU'])
        ->and(listedCodes(['q' => 'ubungo']))->toBe(['DSM'])
        ->and(listedCodes(['q' => 'Sinza']))->toBe(['DSM']);
});

it('filters by stay, monthly price whatever the cycle, bedrooms and amenities', function () {
    landlordWithUnits('mix', ['plan_id' => $this->pro->id], [
        ['code' => 'NIGHTLY', 'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00, 'rent_amount' => 0],
        // 1,800,000 every 6 months = 300,000 a month
        ['code' => 'SIXMONTH', 'billing_cycle' => 'semi_annual', 'rent_amount' => 1_800_000_00, 'bedrooms' => 2, 'amenities' => ['wifi', 'parking']],
        ['code' => 'PRICEY', 'rent_amount' => 900_000_00, 'bedrooms' => 3, 'amenities' => ['wifi']],
    ]);

    expect(listedCodes(['stay' => 'night']))->toBe(['NIGHTLY'])
        ->and(listedCodes(['stay' => 'night', 'max' => 40_000]))->toBe([])
        ->and(listedCodes(['max' => 400_000]))->toBe(['SIXMONTH'])
        ->and(listedCodes(['bedrooms' => 3]))->toBe(['PRICEY'])
        ->and(listedCodes(['amenities' => ['wifi', 'parking']]))->toBe(['SIXMONTH']);
});

it('serves the browse page with links to each landlord room page', function () {
    [$unit] = landlordWithUnits('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101']]);
    landlordWithUnits('demo-ws', ['is_demo' => true], [['code' => 'D9']]);

    $this->get('/browse')
        ->assertOk()
        ->assertSee('Ona nyumba bila kulipa dalali')
        ->assertSee('R101 · Njiro Court')
        ->assertSee('/njiro/units/'.$unit->id, false)
        ->assertSee('Listed by Njiro Homes')
        ->assertSee('Demo listing, not available to book')
        ->assertDontSee('/demo-ws/units/', false)
        ->assertSee('og:title', false);
});

it('filters live on the page', function () {
    landlordWithUnits('arusha', ['plan_id' => $this->pro->id], [['code' => 'ARU']]);
    landlordWithUnits('sinza', ['plan_id' => $this->pro->id], [['code' => 'DSM']], ['name' => 'Sinza', 'district' => 'Ubungo', 'region' => 'Dar es Salaam']);

    Livewire::test(Browse::class)
        ->assertSee('2 places')
        ->set('search', 'Sinza')
        ->assertSee('DSM · Sinza Court')
        ->assertDontSee('ARU · Arusha Court')
        ->call('clearFilters')
        ->assertSee('2 places');
});

it('shows an empty state when nothing matches', function () {
    $this->get('/browse')->assertOk()->assertSee('No places match yet');
});

it('gives each room page a link preview for sharing', function () {
    [$unit] = landlordWithUnits('njiro', ['plan_id' => $this->pro->id], [['code' => 'R101', 'bedrooms' => 1]]);

    $this->get('/njiro/units/'.$unit->id)
        ->assertOk()
        ->assertSee('<meta property="og:title" content="R101 · Njiro Court · Njiro Homes">', false)
        ->assertSee('TZS 300,000 / month · Njiro, Arusha City · 1 bedroom', false);
});
