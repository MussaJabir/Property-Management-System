<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages\Reports;

use App\Models\Property;
use App\Reports\Builders\ArrearsAgeingReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ArrearsAgeingPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Arrears by Age';

    protected static ?string $title = 'Arrears by Age';

    protected static ?int $navigationSort = 22;

    protected function defaultFilters(): array
    {
        return ['as_of' => today()->toDateString()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('as_of')
                    ->label('As of')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(fn (): Carbon => today()->endOfDay())
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
        return new ArrearsAgeingReport(
            propertyId: $this->data['property_id'] ?? null, asOf: filled($this->data['as_of'] ?? null) ? Carbon::parse($this->data['as_of']) : null,
        );
    }
}
