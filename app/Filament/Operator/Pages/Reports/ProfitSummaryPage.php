<?php

namespace App\Filament\Operator\Pages\Reports;

use App\Reports\Builders\ProfitSummaryReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ProfitSummaryPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Profit Summary';

    protected static ?string $title = 'Profit Summary';

    protected static ?int $navigationSort = 60;

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
        return new ProfitSummaryReport(
            from: Carbon::parse($this->data['from'] ?? now()->startOfMonth()),
            to: Carbon::parse($this->data['to'] ?? now()->endOfMonth()),
        );
    }
}
