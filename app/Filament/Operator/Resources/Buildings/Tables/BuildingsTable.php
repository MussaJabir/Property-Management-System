<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Buildings\Tables;

use App\Models\Property;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Building'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('property.name')
                    ->label(__('Property'))
                    ->sortable(),

                TextColumn::make('floors')
                    ->label(__('Floors'))
                    ->placeholder('—'),

                TextColumn::make('units_count')
                    ->label(__('Units'))
                    ->counts('units')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('property_id')
                    ->label(__('Property'))
                    ->options(fn () => Property::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
