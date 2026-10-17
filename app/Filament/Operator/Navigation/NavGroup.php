<?php

declare(strict_types=1);

namespace App\Filament\Operator\Navigation;

use Filament\Support\Contracts\Collapsible;
use Filament\Support\Contracts\HasLabel;

/**
 * Operator sidebar groups, in display order: daily work first, occasional
 * areas last and folded away.
 */
enum NavGroup implements Collapsible, HasLabel
{
    case Billing;
    case RentersLeases;
    case Properties;
    case Reports;
    case Website;
    case SettingsTeam;

    public function getLabel(): string
    {
        return match ($this) {
            self::Billing => __('Billing'),
            self::RentersLeases => __('Renters & leases'),
            self::Properties => __('Properties'),
            self::Reports => __('Reports'),
            self::Website => __('Website'),
            self::SettingsTeam => __('Settings & team'),
        };
    }

    public function isCollapsible(): bool
    {
        return true;
    }

    public function isCollapsed(): bool
    {
        return in_array($this, [self::Reports, self::Website], true);
    }
}
