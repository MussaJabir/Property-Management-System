<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Client;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @param  array{q?: string, stay?: string, max?: int|null, bedrooms?: int|null, amenities?: list<string>, sort?: string}  $filters
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
     * @param  Builder<Unit>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
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
