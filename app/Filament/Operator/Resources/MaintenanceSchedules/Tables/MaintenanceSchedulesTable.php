<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\MaintenanceSchedules\Tables;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MaintenanceSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Job'))
                    ->description(fn (MaintenanceSchedule $s): string => MaintenanceRequest::CATEGORIES[$s->category ?? ''] ?? '')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('unit.code')
                    ->label(__('Unit'))
                    ->description(fn (MaintenanceSchedule $s): ?string => $s->unit?->property?->name),

                TextColumn::make('every')
                    ->label(__('How often'))
                    ->formatStateUsing(fn (MaintenanceSchedule $record): string => $record->frequencyLabel()),

                TextColumn::make('next_due_on')
                    ->label(__('Next due'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (MaintenanceSchedule $record): ?string => $record->isDue() ? 'warning' : null),

                TextColumn::make('contractor.name')
                    ->label(__('Contractor'))
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->recordActions([
                Action::make('createNow')
                    ->label(__('Create work order now'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('primary')
                    ->visible(fn (MaintenanceSchedule $s): bool => $s->is_active && (auth()->user()?->can('update', $s) ?? false))
                    ->requiresConfirmation()
                    ->modalDescription(__('Opens the work order today and moves the next due date on.'))
                    ->action(function (MaintenanceSchedule $record): void {
                        $request = $record->createWorkOrder();
                        Notification::make()->title(__('Work order :number created', ['number' => $request->work_order_number]))->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('next_due_on');
    }
}
