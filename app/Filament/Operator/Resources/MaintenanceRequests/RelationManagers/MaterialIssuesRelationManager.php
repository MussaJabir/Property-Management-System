<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\MaintenanceRequests\RelationManagers;

use App\Filament\Operator\Resources\Materials\Tables\MaterialsTable;
use App\Models\MaintenanceRequest;
use App\Models\MaterialIssue;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Materials taken from stock for this job. Read-only: issuing happens through
 * the "Issue materials" action so stock and cost stay in step.
 */
class MaterialIssuesRelationManager extends RelationManager
{
    protected static string $relationship = 'materialIssues';

    protected static ?string $title = 'Materials used';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('issued_at')
                    ->label(__('Issued'))
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('material.name')
                    ->label(__('Material')),
                TextColumn::make('quantity')
                    ->label(__('Quantity'))
                    ->formatStateUsing(fn (MaterialIssue $record): string => MaterialsTable::qty($record->quantity).' '.strtolower($record->material?->unitLabel() ?? '')),
                TextColumn::make('unit_cost')
                    ->label(__('Each'))
                    ->formatStateUsing(fn (int $state): string => (string) MaintenanceRequest::money($state)),
                TextColumn::make('total_cost')
                    ->label(__('Total'))
                    ->formatStateUsing(fn (int $state): string => (string) MaintenanceRequest::money($state)),
                TextColumn::make('issuedBy.name')
                    ->label(__('By'))
                    ->placeholder('—'),
            ])
            ->defaultSort('issued_at', 'desc');
    }
}
