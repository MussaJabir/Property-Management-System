<?php

namespace App\Filament\Operator\Pages\Reports;

use App\Reports\Builders\PropertyIncomeReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class PropertyIncomePage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Property Income';

    protected static ?string $title = 'Property Income';

    protected static ?int $navigationSort = 40;

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
            ])
            ->statePath('data')
            ->columns(2);
    }

    public function builder(): ReportBuilder
    {
        return new PropertyIncomeReport(
            from: Carbon::parse($this->data['from'] ?? now()->startOfMonth()),
            to: Carbon::parse($this->data['to'] ?? now()->endOfMonth()),
        );
    }
}
