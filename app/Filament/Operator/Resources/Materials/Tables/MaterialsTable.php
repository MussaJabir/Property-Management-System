<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Materials\Tables;

use App\Models\MaintenanceRequest;
use App\Models\Material;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Material'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('stock_quantity')
                    ->label(__('In stock'))
                    ->formatStateUsing(fn (Material $record): string => self::qty($record->stock_quantity).' '.strtolower($record->unitLabel()))
                    ->color(fn (Material $record): ?string => $record->needsReorder() ? 'danger' : null)
                    ->description(fn (Material $record): ?string => $record->needsReorder() ? __('Reorder') : null)
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label(__('Cost of one'))
                    ->formatStateUsing(fn (?int $state): string => MaintenanceRequest::money($state) ?? '—'),

                TextColumn::make('issues_count')
                    ->label(__('Times issued'))
                    ->counts('issues'),
            ])
            ->filters([
                Filter::make('reorder')
                    ->label(__('Needs reorder'))
                    ->query(fn (Builder $q) => $q->whereNotNull('reorder_level')->whereColumn('stock_quantity', '<=', 'reorder_level')),
            ])
            ->recordActions([
                Action::make('restock')
                    ->label(__('Add stock'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('success')
                    ->visible(fn (Material $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->schema([
                        TextInput::make('quantity')->label(__('Quantity received'))->numeric()->minValue(0.01)->required(),
                    ])
                    ->action(function (array $data, Material $record): void {
                        $record->stock_quantity = number_format((float) $record->stock_quantity + (float) $data['quantity'], 2, '.', '');
                        $record->save();
                        Notification::make()->title(__('Stock updated'))->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function qty(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    }
}
