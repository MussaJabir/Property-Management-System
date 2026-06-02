<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Last 10 recorded payments — the "what just happened?" pane on the dashboard.
 */
class RecentPaymentsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent payments';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected function getTableQuery(): Builder|Relation|null
    {
        return Payment::query()
            ->with(['invoice.lease.renter'])
            ->latest('payment_date')
            ->latest('id')
            ->limit(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d/m/Y'),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—'),

                TextColumn::make('invoice.lease.renter.display_name')
                    ->label('Renter')
                    ->placeholder('—'),

                TextColumn::make('formatted_amount')
                    ->label('Amount'),

                TextColumn::make('method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(
                        fn (?string $state): string => str_replace('_', ' ', ucfirst($state ?? 'unknown'))
                    ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }
}
