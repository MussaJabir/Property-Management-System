<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -10;

    public static function getNavigationLabel(): string
    {
        return __('Today');
    }

    public function getTitle(): string
    {
        return __('Today');
    }
}
