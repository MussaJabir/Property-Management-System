<?php

namespace App\Filament\Operator\Resources\Renters\Tables;

use App\Models\Lease;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(query: function ($query, string $search) {
                        $query->where('full_name', 'ilike', "%{$search}%")
                            ->orWhere('business_name', 'ilike', "%{$search}%");
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('full_name', $direction))
                    ->weight('semibold'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'individual' => 'Individual',
                        'business' => 'Business',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'individual' => 'gray',
                        'business' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('email')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('active_leases_count')
                    ->label('Active leases')
                    ->counts([
                        'leases' => fn ($query) => $query->where('status', Lease::STATUS_ACTIVE),
                    ])
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'individual' => 'Individual',
                        'business' => 'Business',
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
            ->defaultSort('full_name');
    }
}
