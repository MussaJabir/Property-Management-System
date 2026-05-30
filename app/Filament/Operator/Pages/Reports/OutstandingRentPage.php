<?php

namespace App\Filament\Operator\Pages\Reports;

use App\Models\Property;
use App\Reports\Builders\OutstandingRentReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OutstandingRentPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Outstanding Rent';

    protected static ?string $title = 'Outstanding Rent';

    protected static ?int $navigationSort = 20;

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
            ->columns(2);
    }

    public function builder(): ReportBuilder
    {
        return new OutstandingRentReport(
            propertyId: $this->data['property_id'] ?? null,
        );
    }
}
