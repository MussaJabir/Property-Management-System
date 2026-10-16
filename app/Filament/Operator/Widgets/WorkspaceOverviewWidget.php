<?php

namespace App\Filament\Operator\Widgets;

use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Filament\Operator\Widgets\Concerns\RefreshesAfterPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Key figures at the top of the Today page, money first:
 *
 *   Collected this month (vs last month, 6-month sparkline) · Outstanding ·
 *   Overdue (how many renters) · Occupancy
 *
 * All queries auto-scope by tenant via TenantScopedModel.
 */
class WorkspaceOverviewWidget extends BaseWidget
{
    use RefreshesAfterPayment;

    // Dashboard order: key figures first. (Widget discovery would otherwise
    // sort alphabetically and push this — "W…" — to the bottom.)
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    // Two cards per row on phones (instead of one long column), four on wide screens.
    protected int|array|null $columns = ['default' => 2, '@xl' => 4, '!@lg' => 4];

    /** Money figures are for roles that can see billing. */
    public static function canView(): bool
    {
        return InvoiceResource::canViewAny();
    }

    protected function getStats(): array
    {
        $fmt = fn (int $cents): string => 'TZS '.number_format($cents / 100, 0, '.', ',');

        /* ---- Collected: this month vs last month + 6-month sparkline ---- */
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $series[] = (int) Payment::query()
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereBetween('payment_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
                ->sum('amount');
        }
        $collectedThisMonth = $series[5];
        $collectedLastMonth = $series[4];
        $lastMonthName = now()->subMonthNoOverflow()->translatedFormat('F');

        if ($collectedLastMonth > 0) {
            $change = (int) round(($collectedThisMonth - $collectedLastMonth) / $collectedLastMonth * 100);
            $collectedNote = __(':change% vs :month', ['change' => ($change >= 0 ? '+' : '').$change, 'month' => $lastMonthName]);
        } else {
            $change = null;
            $collectedNote = __('Nothing collected in :month', ['month' => $lastMonthName]);
        }

        /* ---- Outstanding / overdue (point-in-time receivables) ---- */
        $openInvoices = Invoice::query()->outstanding()->with('lease:id,renter_id')->get();
        $outstanding = $openInvoices->sum(fn (Invoice $i): int => $i->balanceDue());
        $overdueInvoices = $openInvoices->where('status', Invoice::STATUS_OVERDUE);
        $overdue = $overdueInvoices->sum(fn (Invoice $i): int => $i->balanceDue());
        $overdueRenters = $overdueInvoices->pluck('lease.renter_id')->filter()->unique()->count();

        /* ---- Occupancy ---- */
        $unitCount = Unit::count();
        $occupiedCount = Unit::occupied()->count();
        $occupancyRatio = $unitCount > 0 ? $occupiedCount / $unitCount : 0;

        return [
            Stat::make(__('Collected this month'), $fmt($collectedThisMonth))
                ->description($collectedNote)
                ->descriptionIcon(match (true) {
                    $change === null => 'heroicon-m-banknotes',
                    $change >= 0 => 'heroicon-m-arrow-trending-up',
                    default => 'heroicon-m-arrow-trending-down',
                })
                ->chart(array_map(fn (int $c): float => round($c / 100), $series))
                ->color(match (true) {
                    $change === null => 'gray',
                    $change >= 0 => 'success',
                    default => 'danger',
                }),

            Stat::make(__('Outstanding'), $fmt($outstanding))
                ->description(trans_choice(':count open invoice|:count open invoices', $openInvoices->count()))
                ->descriptionIcon('heroicon-m-clock')
                ->color($outstanding > 0 ? 'warning' : 'success'),

            Stat::make(__('Overdue'), $fmt($overdue))
                ->description($overdueRenters > 0
                    ? trans_choice(':count renter past due|:count renters past due', $overdueRenters)
                    : __('Nobody is past due'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'gray'),

            Stat::make(__('Occupancy'), $unitCount > 0 ? round($occupancyRatio * 100).'%' : '—')
                ->description($unitCount > 0
                    ? __(':occupied of :total units occupied', ['occupied' => $occupiedCount, 'total' => $unitCount])
                    : __('Add units to see occupancy'))
                ->descriptionIcon('heroicon-m-key')
                ->color(match (true) {
                    $unitCount === 0 => 'gray',
                    $occupancyRatio >= 0.75 => 'success',
                    $occupancyRatio >= 0.4 => 'warning',
                    default => 'danger',
                }),
        ];
    }
}
