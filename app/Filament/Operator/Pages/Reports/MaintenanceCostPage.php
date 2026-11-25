<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages\Reports;

use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Reports\Builders\MaintenanceCostReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class MaintenanceCostPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Maintenance costs';

    protected static ?string $title = 'Maintenance Cost Report';

    protected static ?int $navigationSort = 55;

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
                Select::make('category')
                    ->label(__('Fault type'))
                    ->placeholder(__('All types'))
                    ->options(MaintenanceRequest::CATEGORIES)
                    ->live(),
            ])
            ->statePath('data')
            ->columns(4);
    }

    public function builder(): ReportBuilder
    {
        return new MaintenanceCostReport(
            from: Carbon::parse($this->data['from'] ?? now()->startOfMonth()),
            to: Carbon::parse($this->data['to'] ?? now()->endOfMonth()),
            propertyId: $this->data['property_id'] ?? null,
            category: $this->data['category'] ?? null,
        );
    }
}
