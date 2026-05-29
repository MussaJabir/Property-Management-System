<?php

namespace App\Filament\Admin\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('slug')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('formatted_price')
                    ->label('Price')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('price_tzs', $direction)),

                TextColumn::make('billing_period')
                    ->badge()
                    ->sortable(),

                TextColumn::make('max_properties')
                    ->label('Max Properties')
                    ->placeholder('Unlimited')
                    ->toggleable(),

                TextColumn::make('max_units')
                    ->label('Max Units')
                    ->placeholder('Unlimited')
                    ->toggleable(),

                IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public'),

                TextColumn::make('clients_count')
                    ->label('Clients')
                    ->counts('clients')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('price_tzs', 'asc');
    }
}
