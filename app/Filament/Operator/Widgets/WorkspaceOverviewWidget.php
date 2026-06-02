<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Single compact KPI row at the top of the operator dashboard. Merges the
 * former inventory + billing-health widgets into four glanceable cards so
 * the dashboard opens without scrolling:
 *
 *   Occupancy · Collected this month (with 6-month sparkline) · Outstanding · Overdue
 *
 * All queries auto-scope by tenant via TenantScopedModel.
 */
class WorkspaceOverviewWidget extends BaseWidget
{
    // Dashboard order: KPI row first. (Widget discovery would otherwise sort
    // alphabetically and push this — "W…" — to the bottom.)
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $fmt = fn (int $cents): string => 'TZS '.number_format($cents / 100, 0, '.', ',');

        /* ---- Occupancy ---- */
        $unitCount = Unit::count();
        $occupiedCount = Unit::occupied()->count();
        $occupancyRatio = $unitCount > 0 ? $occupiedCount / $unitCount : 0;
        $occupancy = $unitCount > 0 ? round($occupancyRatio * 100).'%' : '—';
        $occupancyColor = match (true) {
            $unitCount === 0 => 'gray',
            $occupancyRatio >= 0.75 => 'success',
            $occupancyRatio >= 0.4 => 'warning',
            default => 'danger',
        };

        /* ---- This month collected + 6-month sparkline ---- */
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $series[] = (int) Payment::query()
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');
        }
        $collectedThisMonth = end($series) ?: 0;

        $expectedThisMonth = (int) Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->whereBetween('billing_period_start', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total_amount');

        $moneyColor = match (true) {
            $expectedThisMonth === 0 => 'gray',
            $collectedThisMonth >= $expectedThisMonth => 'success',
            $collectedThisMonth >= ($expectedThisMonth * 0.5) => 'warning',
            default => 'danger',
        };

        /* ---- Outstanding / overdue (point-in-time receivables) ---- */
        $openInvoices = Invoice::query()->outstanding()->get();
        $outstanding = $openInvoices->sum(fn (Invoice $i): int => $i->balanceDue());
        $overdueInvoices = $openInvoices->where('status', Invoice::STATUS_OVERDUE);
        $overdue = $overdueInvoices->sum(fn (Invoice $i): int => $i->balanceDue());

        return [
            Stat::make('Occupancy', $occupancy)
                ->description($unitCount > 0 ? "{$occupiedCount} of {$unitCount} units occupied" : 'Add units to see occupancy')
                ->descriptionIcon('heroicon-m-key')
                ->color($occupancyColor),

            Stat::make('Collected this month', $fmt($collectedThisMonth))
                ->description('Billed: '.$fmt($expectedThisMonth))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart(array_map(fn (int $c): float => round($c / 100), $series))
                ->color($moneyColor),

            Stat::make('Outstanding', $fmt($outstanding))
                ->description($openInvoices->count().' open invoice(s)')
                ->descriptionIcon('heroicon-m-clock')
                ->color($outstanding > 0 ? 'warning' : 'success'),

            Stat::make('Overdue', $fmt($overdue))
                ->description($overdueInvoices->count().' past due')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'gray'),
        ];
    }
}
