<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Unit;
use Filament\Widgets\ChartWidget;

/**
 * Unit status breakdown (occupied / vacant / maintenance / reserved) as a
 * doughnut. Gives an at-a-glance portfolio health read beside collections.
 * Tenant-scoped via the model.
 */
class OccupancyChartWidget extends ChartWidget
{
    protected ?string $heading = 'Units by status';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected function getData(): array
    {
        $counts = Unit::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $buckets = [
            'occupied' => ['label' => 'Occupied', 'color' => '#0F766E'],
            'vacant' => ['label' => 'Vacant', 'color' => '#10B981'],
            'reserved' => ['label' => 'Reserved', 'color' => '#F59E0B'],
            'maintenance' => ['label' => 'Maintenance', 'color' => '#EF4444'],
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($buckets as $status => $meta) {
            $value = (int) ($counts[$status] ?? 0);
            if ($value === 0) {
                continue;
            }
            $labels[] = $meta['label'];
            $data[] = $value;
            $colors[] = $meta['color'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Units',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
