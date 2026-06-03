<?php

namespace App\Filament\Admin\Resources\Subscriptions\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-back of the payments recorded against a subscription. New payments are
 * captured via the "Record payment" action on the subscriptions list (which
 * also extends the paid period), so this manager is view + delete only.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Paid on')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('formatted_amount')
                    ->label('Amount'),

                TextColumn::make('method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => str_replace('_', ' ', ucfirst((string) $state))),

                TextColumn::make('period_start')
                    ->label('Covers')
                    ->formatStateUsing(fn ($state, $record): string => $record->period_start && $record->period_end
                        ? $record->period_start->format('d/m/Y').' → '.$record->period_end->format('d/m/Y')
                        : '—'),

                TextColumn::make('reference')
                    ->label('Ref')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->recordActions([
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No payments recorded yet')
            ->emptyStateDescription('Use "Record payment" on the subscriptions list to log one.');
    }
}
