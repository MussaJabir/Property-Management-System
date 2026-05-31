<?php

namespace App\Filament\Operator\Pages\Reports;

use App\Reports\Builders\OccupancyReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OccupancyPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Occupancy';

    protected static ?string $title = 'Occupancy';

    protected static ?int $navigationSort = 30;

    protected function defaultFilters(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([])->statePath('data');
    }

    public function builder(): ReportBuilder
    {
        return new OccupancyReport;
    }
}
