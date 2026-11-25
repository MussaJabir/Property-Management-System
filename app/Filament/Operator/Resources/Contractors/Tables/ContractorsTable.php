<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Contractors\Tables;

use App\Models\Contractor;
use App\Models\MaintenanceRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContractorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->description(fn (Contractor $c): ?string => $c->company)
                    ->searchable(['name', 'company'])
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('trade')
                    ->label(__('Main work'))
                    ->formatStateUsing(fn (?string $state): string => MaintenanceRequest::CATEGORIES[$state ?? ''] ?? __('Any'))
                    ->placeholder(__('Any')),

                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->placeholder('—'),

                TextColumn::make('maintenance_requests_count')
                    ->label(__('Jobs'))
                    ->counts('maintenanceRequests')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('Available'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('Available')),
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
