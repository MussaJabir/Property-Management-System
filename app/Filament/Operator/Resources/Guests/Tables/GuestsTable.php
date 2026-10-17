<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Guests\Tables;

use App\Models\Guest;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('bookings_max_check_in_date', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('Guest'))
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable(),
                TextColumn::make('id_type')
                    ->label(__('ID'))
                    ->formatStateUsing(fn (?string $state): string => Guest::idTypeOptions()[$state] ?? '—')
                    ->placeholder(__('Not recorded'))
                    ->toggleable(),
                TextColumn::make('bookings_count')
                    ->label(__('Stays'))
                    ->sortable(),
                TextColumn::make('bookings_max_check_in_date')
                    ->label(__('Last stay'))
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? Carbon::parse($state)->format('d/m/Y') : '—')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading(__('No guests yet'))
            ->emptyStateDescription(__('Guests appear here once they are checked in or booked.'));
    }
}
