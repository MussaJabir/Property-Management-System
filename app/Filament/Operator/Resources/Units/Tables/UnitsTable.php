<?php

namespace App\Filament\Operator\Resources\Units\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('property.name')
                    ->label('Property')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'business_frame' => 'Business frame',
                        default => ucfirst($state),
                    })
                    ->color('gray'),

                TextColumn::make('formatted_rent')
                    ->label('Rent')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('rent_amount', $direction)),

                TextColumn::make('billing_cycle')
                    ->label('Cycle')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state, $record): string => match ($state) {
                        'monthly' => 'Monthly',
                        'quarterly' => 'Every 3 months',
                        'semi_annual' => 'Every 6 months',
                        'annual' => 'Yearly',
                        'custom' => 'Every '.($record->billing_cycle_months ?? '?').' months',
                        default => ucfirst($state),
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vacant' => 'success',
                        'occupied' => 'info',
                        'maintenance' => 'warning',
                        'reserved' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'vacant' => 'Vacant',
                        'occupied' => 'Occupied',
                        'maintenance' => 'Maintenance',
                        'reserved' => 'Reserved',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'room' => 'Room',
                        'apartment' => 'Apartment',
                        'business_frame' => 'Business frame',
                        'office' => 'Office',
                        'shop' => 'Shop',
                        'warehouse' => 'Warehouse',
                    ]),
                SelectFilter::make('billing_cycle')
                    ->label('Billing cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'semi_annual' => 'Semi-annual',
                        'annual' => 'Yearly',
                        'custom' => 'Custom',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }
}
