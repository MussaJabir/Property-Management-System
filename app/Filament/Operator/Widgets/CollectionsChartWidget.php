<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;

/**
 * Collected rent over the last 6 months (completed payments). Sits beside
 * the occupancy breakdown on the dashboard. Tenant-scoped via the model.
 */
class CollectionsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Collections — last 6 months';

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected function getData(): array
    {
        $labels = [];
        $totals = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            $labels[] = $start->format('M');
            $totals[] = round(
                (int) Payment::query()
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('amount') / 100
            );
        }

        return [
            'datasets' => [
                [
                    'label' => 'Collected (TZS)',
                    'data' => $totals,
                    'backgroundColor' => '#0F766E',
                    'borderColor' => '#0F766E',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
