<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Client;
use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Platform-wide KPI row for the BJP super-admin dashboard. This panel runs
 * in central (non-tenant) context, so client counts are unscoped. Cross-
 * tenant model counts (units) explicitly drop global scopes so the figure
 * is the whole platform, regardless of tenancy state.
 */
class PlatformOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $total = Client::query()->count();
        $active = Client::query()->where('status', 'active')->count();
        $onTrial = Client::query()->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now())->count();
        $units = Unit::query()->withoutGlobalScopes()->count();
        $newThisMonth = Client::query()->where('created_at', '>=', now()->startOfMonth())->count();

        return [
            Stat::make('Clients', (string) $total)
                ->description($newThisMonth > 0 ? "+{$newThisMonth} this month" : 'No new signups this month')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($total > 0 ? 'success' : 'gray'),

            Stat::make('Active', (string) $active)
                ->description($total > 0 ? round(($active / max($total, 1)) * 100).'% of clients' : '—')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($active > 0 ? 'success' : 'gray'),

            Stat::make('On trial', (string) $onTrial)
                ->description('Trial period not yet ended')
                ->descriptionIcon('heroicon-m-clock')
                ->color($onTrial > 0 ? 'warning' : 'gray'),

            Stat::make('Units (platform)', number_format($units))
                ->description('Across all client workspaces')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info'),
        ];
    }
}
