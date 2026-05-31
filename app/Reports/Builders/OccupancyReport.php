<?php

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Property;
use App\Models\Unit;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Collection;

/**
 * Occupancy — for each property, count of units by status with an occupancy %.
 */
class OccupancyReport implements ReportBuilder
{
    public function meta(): array
    {
        return [
            'title' => 'Occupancy Report',
            'subtitle' => 'As of '.now()->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'property', 'label' => 'Property'],
            ['key' => 'total', 'label' => 'Total units', 'align' => 'right'],
            ['key' => 'occupied', 'label' => 'Occupied', 'align' => 'right'],
            ['key' => 'vacant', 'label' => 'Vacant', 'align' => 'right'],
            ['key' => 'other', 'label' => 'Maintenance / reserved', 'align' => 'right'],
            ['key' => 'occupancy', 'label' => 'Occupancy', 'align' => 'right'],
        ];
    }

    public function rows(): Collection
    {
        return Property::query()
            ->with('units')
            ->orderBy('name')
            ->get()
            ->map(function (Property $property): array {
                $units = $property->units;
                $total = $units->count();
                $occupied = $units->where('status', Unit::STATUS_OCCUPIED)->count();
                $vacant = $units->where('status', Unit::STATUS_VACANT)->count();
                $other = $total - $occupied - $vacant;

                $pct = $total > 0 ? round(($occupied / $total) * 100).'%' : '—';

                return [
                    'property' => $property->name,
                    'total' => number_format($total),
                    'occupied' => number_format($occupied),
                    'vacant' => number_format($vacant),
                    'other' => number_format($other),
                    'occupancy' => $pct,
                ];
            });
    }

    public function summary(): array
    {
        $units = Unit::query()->get();
        $total = $units->count();
        $occupied = $units->where('status', Unit::STATUS_OCCUPIED)->count();
        $pct = $total > 0 ? round(($occupied / $total) * 100).'%' : '—';

        return [
            'Total units' => number_format($total),
            'Total occupied' => number_format($occupied),
            'Portfolio occupancy' => $pct,
        ];
    }

    public function filenameSlug(): string
    {
        return 'occupancy-'.now()->format('Ymd');
    }
}
