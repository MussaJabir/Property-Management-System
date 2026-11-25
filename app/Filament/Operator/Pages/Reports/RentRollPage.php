<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages\Reports;

use App\Models\Property;
use App\Reports\Builders\RentRollReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RentRollPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Rent Roll';

    protected static ?string $title = 'Rent Roll';

    protected static ?int $navigationSort = 5;

    protected function defaultFilters(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('property_id')
                    ->label('Property')
                    ->placeholder('All properties')
                    ->options(fn () => Property::query()->orderBy('name')->pluck('name', 'id'))
                    ->live(),
            ])
            ->statePath('data')
            ->columns(3);
    }

    public function builder(): ReportBuilder
    {
        return new RentRollReport(
            propertyId: $this->data['property_id'] ?? null,
        );
    }
}
