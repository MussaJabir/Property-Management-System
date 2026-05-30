<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Outstanding / overdue health card row, sitting below the inventory +
 * "this month" widget. Both metrics are point-in-time (all-time, not just
 * this month) because that's what landlords actually want to know about
 * receivables risk.
 */
class BillingHealthWidget extends BaseWidget
{
    protected ?string $heading = 'Billing health';

    protected function getStats(): array
    {
        $invoices = Invoice::query()->outstanding()->get();

        $outstanding = $invoices->sum(fn (Invoice $i): int => $i->balanceDue());
        $overdueInvoices = $invoices->where('status', Invoice::STATUS_OVERDUE);
        $overdue = $overdueInvoices->sum(fn (Invoice $i): int => $i->balanceDue());

        $fmt = fn (int $cents): string => 'TZS '.number_format($cents / 100, 0, '.', ',');

        return [
            Stat::make('Overdue', $fmt($overdue))
                ->description($overdueInvoices->count().' invoice(s) past due')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'gray'),

            Stat::make('Outstanding', $fmt($outstanding))
                ->description($invoices->count().' open invoice(s)')
                ->descriptionIcon('heroicon-m-clock')
                ->color($outstanding > 0 ? 'warning' : 'success'),
        ];
    }
}
