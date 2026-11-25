<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages\Reports;

use App\Models\Property;
use App\Reports\Builders\LeaseExpiryReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LeaseExpiryPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Lease Expiry';

    protected static ?string $title = 'Lease Expiry';

    protected static ?int $navigationSort = 25;

    protected function defaultFilters(): array
    {
        return ['days' => 90];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('days')
                    ->label('Ending within')
                    ->options([30 => '30 days', 60 => '60 days', 90 => '90 days', 180 => '6 months', 365 => '12 months'])
                    ->selectablePlaceholder(false)
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
        return new LeaseExpiryReport(
            days: (int) ($this->data['days'] ?? 90), propertyId: $this->data['property_id'] ?? null,
        );
    }
}
