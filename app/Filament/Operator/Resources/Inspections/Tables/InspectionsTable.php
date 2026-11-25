<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Inspections\Tables;

use App\Models\Inspection;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class InspectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inspected_on')
                    ->label(__('Date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (Inspection $record): string => $record->typeLabel())
                    ->color(fn (string $state): string => match ($state) {
                        Inspection::TYPE_MOVE_IN => 'success',
                        Inspection::TYPE_MOVE_OUT => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('unit.code')
                    ->label(__('Unit'))
                    ->description(fn (Inspection $i): ?string => $i->unit?->property?->name),

                TextColumn::make('lease.renter.full_name')
                    ->label(__('Tenant'))
                    ->placeholder('—'),

                TextColumn::make('inspector.name')
                    ->label(__('Inspected by'))
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Inspection::STATUS_COMPLETED ? __('Completed') : __('Draft'))
                    ->color(fn (string $state): string => $state === Inspection::STATUS_COMPLETED ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('Type'))->options(Inspection::typeOptions()),
                SelectFilter::make('status')->label(__('Status'))->options([
                    Inspection::STATUS_DRAFT => __('Draft'),
                    Inspection::STATUS_COMPLETED => __('Completed'),
                ]),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label(__('Complete'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('success')
                    ->visible(fn (Inspection $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(__('Complete this inspection?'))
                    ->modalDescription(__('The report is locked after this and can no longer be changed.'))
                    ->action(function (Inspection $record): void {
                        try {
                            $record->complete();
                            Notification::make()->title(__('Inspection completed'))->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title(__('Could not complete'))->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                ViewAction::make()->visible(fn (Inspection $record): bool => $record->isCompleted()),
            ])
            ->defaultSort('inspected_on', 'desc');
    }
}
