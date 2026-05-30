<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Tables;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Throwable;

class MaintenanceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->limit(60)
                    ->weight('semibold'),

                TextColumn::make('unit.code')
                    ->label('Unit')
                    ->description(fn (MaintenanceRequest $r): ?string => $r->unit?->property?->name)
                    ->searchable(),

                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                TextColumn::make('assignedTo.name')
                    ->label('Assigned')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reported_at')
                    ->label('Reported')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('formatted_cost')
                    ->label('Cost')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Start')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->color('info')
                    ->visible(fn (MaintenanceRequest $r): bool => $r->isPending())
                    ->schema([
                        Select::make('assignee')
                            ->label('Assign to (optional)')
                            ->options(fn () => User::query()
                                ->where('type', 'operator')
                                ->where('status', 'active')
                                ->pluck('name', 'id')),
                        Textarea::make('note')->rows(2)->placeholder('Anything to record?'),
                    ])
                    ->modalHeading('Start this maintenance request?')
                    ->action(function (array $data, MaintenanceRequest $record): void {
                        try {
                            $record->start(
                                userId: auth()->id(),
                                assigneeId: $data['assignee'] ?? null,
                                note: $data['note'] ?? null,
                            );
                            Notification::make()->title('Marked in progress')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not start')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('complete')
                    ->label('Complete')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (MaintenanceRequest $r): bool => $r->isInProgress())
                    ->schema([
                        TextInput::make('cost')
                            ->label('Cost (optional)')
                            ->prefix('TZS')
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->helperText('Whole shillings.')
                            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round(((float) $state) * 100)),
                        Textarea::make('note')->rows(2)->placeholder('Resolution summary'),
                    ])
                    ->modalHeading('Complete this request?')
                    ->action(function (array $data, MaintenanceRequest $record): void {
                        try {
                            $record->complete(
                                userId: auth()->id(),
                                costCents: $data['cost'] ?? null,
                                note: $data['note'] ?? null,
                            );
                            Notification::make()->title('Marked completed')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not complete')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (MaintenanceRequest $r): bool => ! $r->isFinished())
                    ->schema([
                        Textarea::make('reason')
                            ->required()
                            ->rows(2)
                            ->placeholder('Why is this being cancelled?'),
                    ])
                    ->modalHeading('Cancel this request?')
                    ->action(function (array $data, MaintenanceRequest $record): void {
                        try {
                            $record->cancel(auth()->id(), $data['reason'] ?? null);
                            Notification::make()->title('Cancelled')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not cancel')->body($e->getMessage())->danger()->send();
                        }
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('reported_at', 'desc');
    }
}
