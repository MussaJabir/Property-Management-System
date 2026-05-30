<?php

namespace App\Filament\Operator\Pages\Reports;

use App\Models\Property;
use App\Reports\Builders\MonthlyRentCollectionReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class MonthlyRentCollectionPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Monthly Rent Collection';

    protected static ?string $title = 'Monthly Rent Collection';

    protected static ?int $navigationSort = 10;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('from')
                    ->label('From')
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->live(),
                DatePicker::make('to')
                    ->label('To')
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->afterOrEqual('from')
                    ->live(),
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
        return new MonthlyRentCollectionReport(
            from: Carbon::parse($this->data['from'] ?? now()->startOfMonth()),
            to: Carbon::parse($this->data['to'] ?? now()->endOfMonth()),
            propertyId: $this->data['property_id'] ?? null,
        );
    }
}
