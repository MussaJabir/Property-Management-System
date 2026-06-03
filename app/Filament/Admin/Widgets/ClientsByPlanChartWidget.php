<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Client;
use App\Models\Plan;
use Filament\Widgets\ChartWidget;

/**
 * How clients are distributed across plans — quick read on which tiers are
 * selling. Central context, so no tenant scoping needed.
 */
class ClientsByPlanChartWidget extends ChartWidget
{
    protected ?string $heading = 'Clients by plan';

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected function getData(): array
    {
        $palette = ['#0F766E', '#14B8A6', '#0369A1', '#F59E0B', '#7C3AED', '#EF4444'];

        $plans = Plan::query()->orderBy('name')->get();

        $labels = [];
        $data = [];
        $colors = [];
        $i = 0;

        foreach ($plans as $plan) {
            $count = Client::query()->where('plan_id', $plan->id)->count();
            if ($count === 0) {
                continue;
            }
            $labels[] = $plan->name;
            $data[] = $count;
            $colors[] = $palette[$i % count($palette)];
            $i++;
        }

        // Clients with no plan assigned.
        $noPlan = Client::query()->whereNull('plan_id')->count();
        if ($noPlan > 0) {
            $labels[] = 'No plan';
            $data[] = $noPlan;
            $colors[] = '#9CA3AF';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Clients',
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
