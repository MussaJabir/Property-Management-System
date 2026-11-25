<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\RentalApplications\Tables;

use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\RentalApplication;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class RentalApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('applicant_name')
                    ->label(__('Applicant'))
                    ->description(fn (RentalApplication $a): string => (string) $a->phone)
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('unit.code')
                    ->label(__('Unit'))
                    ->description(fn (RentalApplication $a): ?string => $a->unit?->property?->name)
                    ->placeholder('—'),

                TextColumn::make('move_in_on')
                    ->label(__('Move in'))
                    ->date('d/m/Y')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (RentalApplication $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        RentalApplication::STATUS_RECEIVED => 'warning',
                        RentalApplication::STATUS_APPROVED => 'success',
                        RentalApplication::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label(__('Received'))
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(RentalApplication::statusOptions()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (RentalApplication $a): bool => $a->isOpen() && (auth()->user()?->can('update', $a) ?? false))
                    ->schema([
                        Textarea::make('reason')->label(__('Note (optional)'))->rows(2),
                    ])
                    ->modalHeading(__('Approve this applicant?'))
                    ->modalDescription(__('They are added to Renters (or linked if they are already there), ready for a lease.'))
                    ->action(function (array $data, RentalApplication $record): void {
                        try {
                            $renter = $record->approve(auth()->id(), $data['reason'] ?? null);
                            Notification::make()
                                ->title(__('Approved'))
                                ->body(__(':name is in Renters. Create their lease next.', ['name' => $renter->full_name]))
                                ->success()
                                ->actions([
                                    Action::make('open')->label(__('Open renter'))->url(RenterResource::getUrl('edit', ['record' => $renter])),
                                ])
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title(__('Could not approve'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('reject')
                    ->label(__('Decline'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (RentalApplication $a): bool => $a->isOpen() && (auth()->user()?->can('update', $a) ?? false))
                    ->schema([
                        Textarea::make('reason')->label(__('Reason'))->required()->rows(2),
                    ])
                    ->modalHeading(__('Decline this application?'))
                    ->action(function (array $data, RentalApplication $record): void {
                        try {
                            $record->reject(auth()->id(), $data['reason'] ?? null);
                            Notification::make()->title(__('Declined'))->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title(__('Could not decline'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('withdraw')
                    ->label(__('Withdrawn'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (RentalApplication $a): bool => $a->isOpen() && (auth()->user()?->can('update', $a) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(__('Mark as withdrawn by the applicant?'))
                    ->action(function (RentalApplication $record): void {
                        $record->withdraw(auth()->id());
                        Notification::make()->title(__('Marked as withdrawn'))->success()->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
