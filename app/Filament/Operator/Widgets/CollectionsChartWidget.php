<?php

namespace App\Filament\Operator\Widgets;

use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Rent billed (issued invoices by billing month) next to rent collected
 * (completed payments by payment date) over the last 6 months, so a gap
 * between the two bars shows at a glance. Tenant-scoped via the models.
 */
class CollectionsChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 3];

    /** Money figures are for roles that can see billing. */
    public static function canView(): bool
    {
        return InvoiceResource::canViewAny();
    }

    public function getHeading(): string
    {
        return __('Rent billed and collected');
    }

    public function getDescription(): string
    {
        return __('Last 6 months · TZS');
    }

    protected function getData(): array
    {
        $labels = [];
        $billed = [];
        $collected = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $range = [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];

            $labels[] = $start->translatedFormat('M');
            $billed[] = round((int) Invoice::query()
                ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
                ->whereBetween('billing_period_start', $range)
                ->sum('total_amount') / 100);
            $collected[] = round((int) Payment::query()
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereBetween('payment_date', $range)
                ->sum('amount') / 100);
        }

        return [
            'datasets' => [
                [
                    'label' => __('Billed'),
                    'data' => $billed,
                    'backgroundColor' => '#99D5CF',
                    'borderColor' => '#99D5CF',
                    'borderRadius' => 5,
                    'maxBarThickness' => 18,
                ],
                [
                    'label' => __('Collected'),
                    'data' => $collected,
                    'backgroundColor' => '#0F766E',
                    'borderColor' => '#0F766E',
                    'borderRadius' => 5,
                    'maxBarThickness' => 18,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        // Short axis labels (18M, 500K) so the numbers don't crowd the bars.
        return RawJs::make(<<<'JS'
            {
                plugins: { legend: { display: true, position: 'top', align: 'start' } },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => value >= 1e6 ? (value / 1e6) + 'M' : (value >= 1e3 ? (value / 1e3) + 'K' : value),
                        },
                    },
                },
            }
        JS);
    }
}
