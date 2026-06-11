<?php

namespace App\Filament\Admin\Resources\Clients\Tables;

use App\Filament\Admin\Resources\Clients\Actions\PurgeClientAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClientsTable
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
                    ->label('URL')
                    ->prefix('/')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('URL copied')
                    ->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('contact_email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'active' || $record->status === 'trial')
                    ->action(fn ($record) => $record->update(['status' => 'suspended'])),

                Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'suspended' || $record->status === 'trial')
                    ->action(fn ($record) => $record->update(['status' => 'active'])),

                RestoreAction::make(),

                // Permanent wipe — typed-name confirmation, archived clients only.
                PurgeClientAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Soft archive + restore only. Permanent deletion is a
                    // deliberate, per-client, typed-confirmation purge — never a
                    // bulk one-click action.
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
