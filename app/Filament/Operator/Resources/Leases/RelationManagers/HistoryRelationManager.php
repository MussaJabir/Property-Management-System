<?php

namespace App\Filament\Operator\Resources\Leases\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Append-only audit timeline for a lease (activated / terminated / ended /
 * renewed / rent_changed). Rendered as a tab on the Lease edit page.
 *
 * Read-only: rows are created exclusively by Lease::activate() / terminate() /
 * end() — never from the UI. We don't expose create/edit/delete actions.
 */
class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'history';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'gray',
                        'activated' => 'success',
                        'renewed' => 'info',
                        'rent_changed' => 'warning',
                        'ended' => 'gray',
                        'terminated' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(),

                TextColumn::make('reason')
                    ->wrap()
                    ->placeholder('—')
                    ->limit(80),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'created' => 'Created',
                        'activated' => 'Activated',
                        'renewed' => 'Renewed',
                        'rent_changed' => 'Rent changed',
                        'ended' => 'Ended',
                        'terminated' => 'Terminated',
                    ]),
            ])
            ->headerActions([
                // Intentionally empty — audit rows are written by the model only.
            ])
            ->recordActions([
                // Each row already shows action / who / when / reason; no
                // detail modal needed. Raw before/after JSON is developer-only
                // and would only confuse operators.
            ])
            ->toolbarActions([
                // No bulk actions — entries are immutable.
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function form(Schema $schema): Schema
    {
        // The relation manager base requires a form() method even when we
        // don't expose create/edit actions. Returning an empty schema is fine.
        return $schema->components([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
