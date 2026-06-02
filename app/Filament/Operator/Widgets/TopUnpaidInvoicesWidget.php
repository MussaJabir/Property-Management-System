<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Top 5 invoices with the largest outstanding balance. Quick "who do I
 * chase first" view for the dashboard.
 */
class TopUnpaidInvoicesWidget extends TableWidget
{
    protected static ?string $heading = 'Top 5 unpaid invoices';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected function getTableQuery(): Builder
    {
        return Invoice::query()
            ->outstanding()
            ->with(['lease.renter', 'lease.unit.property'])
            ->orderByRaw('(total_amount - amount_paid) DESC')
            ->limit(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Number')
                    ->placeholder('— draft —')
                    ->weight('semibold'),

                TextColumn::make('lease.renter.display_name')
                    ->label('Renter'),

                TextColumn::make('lease.unit.code')
                    ->label('Unit')
                    ->description(fn (Invoice $r): ?string => $r->lease?->unit?->property?->name),

                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d/m/Y'),

                TextColumn::make('formatted_balance')
                    ->label('Outstanding'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'overdue' => 'danger',
                        'partial' => 'info',
                        'unpaid' => 'warning',
                        default => 'gray',
                    }),
            ]);
    }
}
