<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Property;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard stat cards for an operator's workspace.
 *
 * Properties / Units / Occupancy queries are real (Phase 3). The money stat
 * stays placeholder until Phase 5 (Billing) lands.
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

            Stat::make('This month', 'TZS 0 / TZS 0')
                ->description('Collected / expected — wires up in Phase 5')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('gray'),
        ];
    }
}
