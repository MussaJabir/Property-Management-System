<?php

namespace App\Filament\Operator\Resources\Expenses\Tables;

use App\Models\ExpenseCategory;
use App\Models\Property;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record): string => 'gray')
                    ->searchable(),

                TextColumn::make('property.name')
                    ->label('Property')
                    ->placeholder('— General —')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->sortable(query: fn ($q, string $d) => $q->orderBy('amount', $d)),

                TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => ExpenseCategory::query()->orderBy('name')->pluck('name', 'id')),

                SelectFilter::make('property_id')
                    ->label('Property')
                    ->options(fn () => Property::query()->orderBy('name')->pluck('name', 'id')),

                Filter::make('this_month')
                    ->label('This month')
                    ->query(fn ($query) => $query->whereBetween('expense_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])),

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
            ->defaultSort('expense_date', 'desc');
    }
}
