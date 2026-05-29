<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard stat cards for an operator's workspace.
 *
 * - Properties / Units / Occupancy come from Phase 3 inventory.
 * - "This month" pulls from Phase 5 billing:
 *      collected = sum of completed payments dated in the current month
 *      expected  = sum of total_amount on issued (non-draft, non-cancelled)
 *                  invoices whose billing_period_start falls in the current
 *                  month (i.e. invoices billing "for" this month — independent
 *                  of any grace window the due_date adds)
 *
 * All queries auto-scope by tenant via TenantScopedModel.
 */
class WorkspaceOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $propertyCount = Property::count();
        $unitCount = Unit::count();
        $occupiedCount = Unit::occupied()->count();

        $occupancy = $unitCount > 0
            ? round(($occupiedCount / $unitCount) * 100).'%'
            : '—';

        $occupancyColor = match (true) {
            $unitCount === 0 => 'gray',
            $occupiedCount / $unitCount >= 0.75 => 'success',
            $occupiedCount / $unitCount >= 0.4 => 'warning',
            default => 'danger',
        };

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $collectedCents = (int) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $expectedCents = (int) Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED])
            ->whereBetween('billing_period_start', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('total_amount');

        $fmt = fn (int $cents): string => 'TZS '.number_format($cents / 100, 0, '.', ',');

        // Color the money card by collection ratio — gray if nothing's expected.
        $moneyColor = match (true) {
            $expectedCents === 0 => 'gray',
            $collectedCents >= $expectedCents => 'success',
            $collectedCents >= ($expectedCents * 0.5) => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Properties', (string) $propertyCount)
                ->description('Buildings / compounds you manage')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($propertyCount > 0 ? 'success' : 'gray'),

            Stat::make('Units', (string) $unitCount)
                ->description('Rentable units across all properties')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color($unitCount > 0 ? 'success' : 'gray'),

            Stat::make('Occupancy', $occupancy)
                ->description($unitCount > 0 ? "{$occupiedCount} of {$unitCount} occupied" : 'Add units to see occupancy')
                ->descriptionIcon('heroicon-m-key')
                ->color($occupancyColor),

            Stat::make('This month', $fmt($collectedCents).' / '.$fmt($expectedCents))
                ->description('Collected / billed for '.$monthStart->format('F'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($moneyColor),
        ];
    }
}
