<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Client;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\TenantScope;

/**
 * The PMS marketplace (/browse): vacant units from every client that opted
 * them in. This is the ONE place PMS reads units across clients on purpose;
 * the browse page (and later the renter app's API) use it, nothing else.
 *
 * A unit is listed when:
 *  - its landlord switched it on (on_marketplace),
 *  - it is free to let (Unit::scopeListedPublicly),
 *  - its client is active and on a plan with `marketplace_listing`,
 *    or is a demo workspace (shown last, labelled, unless hidden).
 */
final class ListingSearch
{
    public const HIDE_DEMO_SETTING = 'marketplace.hide_demo';

    public const PER_PAGE = 12;

    public const SORT_FEATURED = 'featured';

    public const SORT_NEWEST = 'newest';

    public const SORT_PRICE = 'price';

    /** The /browse filters, as they appear in its address. */
    public const QUERY_KEYS = ['q', 'stay', 'max', 'bedrooms', 'amenities', 'sort', 'landlord', 'type'];

    /** Kinds of place a visitor can pick (the chips on phones). */
    public const TYPE_GROUPS = [
        'rooms' => [Unit::TYPE_ROOM],
        'apartments' => [Unit::TYPE_APARTMENT],
        'business' => [Unit::TYPE_BUSINESS_FRAME, Unit::TYPE_SHOP, Unit::TYPE_OFFICE, Unit::TYPE_WAREHOUSE],
    ];

    /**
     * Only the known /browse filters that are set, so a room page can link
     * back to the same search.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function browseQuery(array $input): array
    {
        $query = [];

        foreach (self::QUERY_KEYS as $key) {
            $value = $input[$key] ?? null;

            if ($key === 'amenities') {
                $value = array_values(array_intersect((array) $value, Unit::AMENITIES));
            } elseif (is_scalar($value)) {
                $value = trim((string) $value);
            } else {
                $value = null;
            }

            if ($value !== null && $value !== '' && $value !== [] && ! ($key === 'sort' && $value === self::SORT_FEATURED)) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * @param  array{q?: string, stay?: string, max?: int|null, bedrooms?: int|null, amenities?: list<string>, sort?: string, landlord?: string, type?: string}  $filters
     * @return LengthAwarePaginator<int, Unit>
     */
    public function search(array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $this->listed()->with(['tenant', 'property.location', 'media', 'property.media']);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? self::SORT_FEATURED);

        return $query->paginate($perPage);
    }

    /**
     * Every listed unit, before any visitor filter.
     *
     * @return Builder<Unit>
     */
    public function listed(): Builder
    {
        $hideDemo = (bool) SystemSetting::value(self::HIDE_DEMO_SETTING, false);

        $clients = Client::query()
            ->where(function (Builder $q) use ($hideDemo): void {
                $q->where(fn (Builder $live) => $live
                    ->where('status', 'active')
                    ->where('is_demo', false)
                    ->whereIn('plan_id', Plan::query()->whereJsonContains('features', 'marketplace_listing')->select('id')));

                if (! $hideDemo) {
                    $q->orWhere('is_demo', true);
                }
            })
            ->select('id');

        return Unit::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('units.on_marketplace', true)
            ->whereIn('units.tenant_id', $clients)
            ->listedPublicly();
    }

    /**
     * The phone home screen: one row per area, busiest areas first, each
     * row featured first. Only the given filters (e.g. a type chip) apply.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{region: string, total: int, units: Collection<int, Unit>}>
     */
    public function rowsByRegion(array $filters = [], int $regions = 4, int $perRow = 8): array
    {
        $counted = $this->listed();
        $this->applyFilters($counted, $filters);

        $busiest = DB::table('locations')
            ->join('properties', 'properties.location_id', '=', 'locations.id')
            ->join('units', 'units.property_id', '=', 'properties.id')
            ->whereIn('units.id', $counted->select('units.id'))
            ->whereNull('properties.deleted_at')
            ->whereNull('locations.deleted_at')
            ->whereNotNull('locations.region')
            ->groupBy('locations.region')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('locations.region')
            ->limit($regions)
            ->get(['locations.region', DB::raw('COUNT(*) AS total')]);

        $rows = [];

        foreach ($busiest as $row) {
            $query = $this->listed()->with(['tenant', 'property.location', 'media', 'property.media']);
            $this->applyFilters($query, $filters);
            $query->whereHas('property', fn (Builder $p) => $p
                ->withoutGlobalScope(TenantScope::class)
                ->whereHas('location', fn (Builder $l) => $l
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('region', $row->region)));
            $this->applySort($query, self::SORT_FEATURED);

            $rows[] = ['region' => (string) $row->region, 'total' => (int) $row->total, 'units' => $query->limit($perRow)->get()];
        }

        return $rows;
    }

    /**
     * How many places match.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        $query = $this->listed();
        $this->applyFilters($query, $filters);

        return $query->count();
    }

    /** Whether this unit shows on the marketplace right now. */
    public function isListed(Unit $unit): bool
    {
        return $this->listed()->whereKey($unit->getKey())->exists();
    }

    /** How many places a landlord has on the marketplace. */
    public function countFor(Client $client): int
    {
        return $this->listed()->where('units.tenant_id', $client->getKey())->count();
    }

    /**
     * Other listed places in the same region, from any landlord: featured
     * first, never demo ones. Runs outside any workspace so every landlord's
     * rooms (and their property and photos) load.
     *
     * @return Collection<int, Unit>
     */
    public function nearby(Unit $unit, int $limit = 4): Collection
    {
        $region = $unit->property?->location?->region;

        return tenancy()->central(function () use ($unit, $region, $limit): Collection {
            $query = $this->listed()
                ->whereKeyNot($unit->getKey())
                ->whereIn('units.tenant_id', Client::query()->where('is_demo', false)->select('id'))
                ->with(['tenant', 'property.location', 'media', 'property.media']);

            if ($region) {
                $query->whereHas('property', fn (Builder $p) => $p
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereHas('location', fn (Builder $l) => $l
                        ->withoutGlobalScope(TenantScope::class)
                        ->where('region', $region)));
            }

            $this->applySort($query, self::SORT_FEATURED);

            return $query->limit($limit)->get();
        });
    }

    /**
     * @param  Builder<Unit>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $type = (string) ($filters['type'] ?? '');

        if (isset(self::TYPE_GROUPS[$type])) {
            $query->whereIn('units.type', self::TYPE_GROUPS[$type]);
        }

        $landlord = trim((string) ($filters['landlord'] ?? ''));

        if ($landlord !== '') {
            $query->where('units.tenant_id', $landlord);
        }

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn (Builder $q) => $q
                ->where('units.code', 'ilike', $like)
                ->orWhere('units.description', 'ilike', $like)
                ->orWhereHas('property', fn (Builder $p) => $p
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('name', 'ilike', $like)
                    ->orWhereHas('location', fn (Builder $l) => $l
                        ->withoutGlobalScope(TenantScope::class)
                        ->where(fn (Builder $w) => $w
                            ->where('name', 'ilike', $like)
                            ->orWhere('ward', 'ilike', $like)
                            ->orWhere('district', 'ilike', $like)
                            ->orWhere('region', 'ilike', $like)))));
        }

        $stay = (string) ($filters['stay'] ?? '');

        match ($stay) {
            'night' => $query->shortStay(),
            'month' => $query->longTerm(),
            default => null,
        };

        $max = (int) ($filters['max'] ?? 0);

        if ($max > 0) {
            $cents = $max * 100;

            if ($stay === 'night') {
                $query->where('units.nightly_rate', '<=', $cents);
            } else {
                // Compare the monthly amount, whatever the billing cycle.
                $query->longTerm()->whereRaw($this->monthlyRentSql().' <= ?', [$cents]);
            }
        }

        $bedrooms = (int) ($filters['bedrooms'] ?? 0);

        if ($bedrooms > 0) {
            $query->where('units.bedrooms', '>=', $bedrooms);
        }

        foreach (array_intersect((array) ($filters['amenities'] ?? []), Unit::AMENITIES) as $amenity) {
            $query->whereJsonContains('units.amenities', $amenity);
        }
    }

    /** @param  Builder<Unit>  $query */
    private function applySort(Builder $query, string $sort): void
    {
        // Demo listings always come after real ones.
        $query->orderBy(
            Client::query()->select('is_demo')->whereColumn('tenants.id', 'units.tenant_id')->limit(1)
        );

        if ($sort === self::SORT_PRICE) {
            $query->orderByRaw('COALESCE(NULLIF('.$this->monthlyRentSql().', 0), units.nightly_rate * 30) ASC');
        } elseif ($sort !== self::SORT_NEWEST) {
            $query->orderByRaw('CASE WHEN units.featured_until > ? THEN 0 ELSE 1 END', [now()]);
        }

        $query->orderByDesc('units.updated_at');
    }

    /** Rent per month in cents, from the per-period rent and the cycle. */
    private function monthlyRentSql(): string
    {
        return "(units.rent_amount / (CASE units.billing_cycle
            WHEN 'quarterly' THEN 3
            WHEN 'semi_annual' THEN 6
            WHEN 'annual' THEN 12
            WHEN 'custom' THEN GREATEST(COALESCE(units.billing_cycle_months, 1), 1)
            ELSE 1 END))";
    }

    /**
     * Areas for the phone search panel: each region with how many places it
     * has and the cheapest TZS price (per month, else per night). USD-only
     * areas get no price, so currencies are never compared.
     *
     * @return list<array{region: string, total: int, from: int|null, per: string|null}>
     */
    public function regionSummaries(): array
    {
        $monthly = $this->monthlyRentSql();
        $short = "'".Unit::RENTAL_SHORT_STAY."'";
        $both = "'".Unit::RENTAL_BOTH."'";

        return DB::table('units')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->join('locations', 'locations.id', '=', 'properties.location_id')
            ->whereIn('units.id', $this->listed()->select('units.id'))
            ->whereNull('properties.deleted_at')
            ->whereNull('locations.deleted_at')
            ->whereNotNull('locations.region')
            ->groupBy('locations.region')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('locations.region')
            ->get([
                'locations.region',
                DB::raw('COUNT(*) AS total'),
                DB::raw("MIN(CASE WHEN units.rent_currency = 'TZS' AND units.rental_mode <> {$short} AND units.rent_amount > 0 THEN {$monthly} END) AS min_month"),
                DB::raw("MIN(CASE WHEN units.rent_currency = 'TZS' AND units.rental_mode IN ({$short}, {$both}) AND units.nightly_rate > 0 THEN units.nightly_rate END) AS min_night"),
            ])
            ->map(fn (object $row): array => [
                'region' => (string) $row->region,
                'total' => (int) $row->total,
                'from' => $row->min_month !== null ? (int) round((float) $row->min_month / 100) : ($row->min_night !== null ? (int) round((float) $row->min_night / 100) : null),
                'per' => $row->min_month !== null ? 'month' : ($row->min_night !== null ? 'night' : null),
            ])
            ->values()
            ->all();
    }

    /**
     * Distinct regions with listings, for the area suggestions.
     *
     * @return list<string>
     */
    public function regions(): array
    {
        return DB::table('locations')
            ->join('properties', 'properties.location_id', '=', 'locations.id')
            ->whereIn('properties.id', $this->listed()->select('units.property_id'))
            ->whereNull('locations.deleted_at')
            ->distinct()
            ->orderBy('locations.region')
            ->pluck('locations.region')
            ->filter()
            ->values()
            ->all();
    }
}
