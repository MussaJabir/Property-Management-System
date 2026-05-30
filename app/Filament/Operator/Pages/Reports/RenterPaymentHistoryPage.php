<?php

namespace App\Filament\Operator\Pages\Reports;

use App\Models\Renter;
use App\Reports\Builders\RenterPaymentHistoryReport;
use App\Reports\Contracts\ReportBuilder;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RenterPaymentHistoryPage extends BaseReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'Renter Payment History';

    protected static ?string $title = 'Renter Payment History';

    protected static ?int $navigationSort = 70;

    protected function defaultFilters(): array
    {
        // Default to the first renter if any exist, otherwise empty.
        $first = Renter::query()->orderBy('full_name')->first();

        return [
            'renter_id' => $first?->id,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('renter_id')
                    ->label('Renter')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn () => Renter::query()
                        ->orderBy('full_name')
                        ->get()
                        ->mapWithKeys(fn (Renter $r): array => [$r->id => $r->display_name]))
                    ->live(),
            ])
            ->statePath('data')
            ->columns(1);
    }

    public function builder(): ReportBuilder
    {
        $renterId = $this->data['renter_id'] ?? null;

        if (! $renterId) {
            // Edge case: no renter chosen → use a sentinel UUID so all queries
            // return empty (instead of throwing).
            $renterId = '00000000-0000-0000-0000-000000000000';
        }

        return new RenterPaymentHistoryReport($renterId);
    }
}
