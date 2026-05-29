<?php

namespace App\Filament\Operator\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard stat cards for an operator's workspace.
 *
 * Placeholder values until Phase 3 (Properties + Units) lands. The query
 * helpers will be filled in then:
 *   - Property::count()
 *   - Unit::where('status', 'occupied')->count() / Unit::count() for occupancy %
 *   - Invoice::whereBetween(billing_period, [start, end])->sum('total_amount')
 */
class WorkspaceOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Properties', '0')
                ->description('Total buildings / compounds')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('gray'),

            Stat::make('Units', '0')
                ->description('Rentable units across all properties')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('gray'),

            Stat::make('Occupancy', '—')
                ->description('% of units currently occupied')
                ->descriptionIcon('heroicon-m-key')
                ->color('gray'),

            Stat::make('This month', 'TZS 0 / TZS 0')
                ->description('Collected / expected')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),
        ];
    }
}
