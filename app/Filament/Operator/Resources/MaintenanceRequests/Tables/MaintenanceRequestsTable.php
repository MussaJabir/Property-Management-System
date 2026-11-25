<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Tables;

use App\Filament\Operator\Resources\Materials\Tables\MaterialsTable;
use App\Models\Contractor;
use App\Models\MaintenanceRequest;
use App\Models\Material;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    ->weight('semibold')
                    ->description(fn (MaintenanceRequest $r): ?string => $r->work_order_number),

                TextColumn::make('category')
                    ->label(__('Fault type'))
                    ->formatStateUsing(fn (?string $state): string => MaintenanceRequest::CATEGORIES[$state ?? ''] ?? '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        'approved' => 'primary',
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                TextColumn::make('assignedTo.name')
                    ->label('Assigned')
                    ->description(fn (MaintenanceRequest $r): ?string => $r->contractor?->displayName())
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('reported_at')
                    ->label('Reported')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('due_on')
                    ->label(__('Due'))
                    ->state(fn (MaintenanceRequest $r): ?string => $r->isFinished() ? null : $r->dueDate()->format('d/m/Y'))
                    ->color(fn (MaintenanceRequest $r): ?string => $r->isOverdue() ? 'danger' : null)
                    ->description(fn (MaintenanceRequest $r): ?string => $r->isOverdue() ? __('Overdue') : null)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('formatted_cost')
                    ->label('Cost')
                    ->description(fn (MaintenanceRequest $r): ?string => $r->estimated_cost !== null ? __('Estimate').' '.$r->formatted_estimated_cost : null)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('signed_off_at')
                    ->label(__('Signed off'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
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
                Filter::make('overdue')
                    ->label(__('Overdue'))
                    ->query(fn (Builder $q) => $q->scopes(['overdue'])),
                Filter::make('repeat')
                    ->label(__('Repeat faults (same fault, same unit, :days days)', ['days' => MaintenanceRequest::REPEAT_DAYS]))
                    ->query(fn (Builder $q) => $q->scopes(['repeatFaults'])),
                SelectFilter::make('category')
                    ->label(__('Fault type'))
                    ->options(MaintenanceRequest::CATEGORIES),
                SelectFilter::make('contractor_id')
                    ->label(__('Contractor'))
                    ->options(fn (): array => Contractor::query()->orderBy('name')->pluck('name', 'id')->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Approve'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('primary')
                    ->visible(fn (MaintenanceRequest $r): bool => $r->isPending() && (auth()->user()?->can('update', $r) ?? false))
                    ->schema([
                        Select::make('contractor')
                            ->label(__('Contractor (optional)'))
                            ->options(fn (): array => Contractor::query()->active()->orderBy('name')->get()
                                ->mapWithKeys(fn (Contractor $c): array => [$c->id => $c->displayName()])->all())
                            ->searchable(),
                        Select::make('assignee')
                            ->label(__('Staff member (optional)'))
                            ->options(fn () => User::query()
                                ->where('tenant_id', tenant('id'))
                                ->where('type', 'operator')
                                ->where('status', 'active')
                                ->pluck('name', 'id')),
                        self::moneyInput('estimate', __('Estimated cost (optional)')),
                        DatePicker::make('due_on')
                            ->label(__('Finish by'))
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(fn (MaintenanceRequest $record) => today()->addDays(MaintenanceRequest::DAYS_TO_FIX[$record->priority] ?? 7))
                            ->helperText(__('Set from the priority; change it if needed.')),
                        Textarea::make('note')->rows(2)->placeholder(__('Instructions for the work')),
                    ])
                    ->modalHeading(__('Approve and create a work order?'))
                    ->action(function (array $data, MaintenanceRequest $record): void {
                        try {
                            $record->approve(
                                userId: auth()->id(),
                                contractorId: filled($data['contractor'] ?? null) ? (int) $data['contractor'] : null,
                                assigneeId: filled($data['assignee'] ?? null) ? (int) $data['assignee'] : null,
                                estimatedCents: $data['estimate'] ?? null,
                                note: $data['note'] ?? null,
                                dueOn: $data['due_on'] ?? null,
                            );
                            Notification::make()->title(__('Work order :number created', ['number' => $record->work_order_number]))->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title(__('Could not approve'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('start')
                    ->label('Start')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->color('info')
                    ->visible(fn (MaintenanceRequest $r): bool => $r->isPending() || $r->isApproved())
                    ->schema([
                        Select::make('assignee')
                            ->label('Assign to (optional)')
                            // Scope to THIS client's operators — User has no global
                            // tenant scope, so filter by tenant_id or it leaks
                            // every client's staff (see the form schema).
                            ->options(fn () => User::query()
                                ->where('tenant_id', tenant('id'))
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

                Action::make('issueMaterials')
                    ->label(__('Issue materials'))
                    ->icon(Heroicon::OutlinedCube)
                    ->color('gray')
                    ->visible(fn (MaintenanceRequest $r): bool => ($r->isApproved() || $r->isInProgress()) && (auth()->user()?->can('update', $r) ?? false))
                    ->schema([
                        Select::make('material')
                            ->label(__('Material'))
                            ->options(fn (): array => Material::query()->orderBy('name')->get()
                                ->mapWithKeys(fn (Material $m): array => [$m->id => $m->name.' — '.MaterialsTable::qty($m->stock_quantity).' '.strtolower($m->unitLabel()).' '.__('in stock')])->all())
                            ->required()
                            ->searchable(),
                        TextInput::make('quantity')
                            ->label(__('Quantity'))
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                    ])
                    ->modalHeading(__('Issue materials to this job'))
                    ->modalDescription(__('Taken from stock and added to the job\'s materials cost.'))
                    ->action(function (array $data, MaintenanceRequest $record): void {
                        try {
                            $material = Material::query()->findOrFail($data['material']);
                            $issue = $record->issueMaterial($material, (float) $data['quantity'], auth()->id());
                            Notification::make()
                                ->title(__('Materials issued'))
                                ->body(MaintenanceRequest::money($issue->total_cost))
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()->title(__('Could not issue materials'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('complete')
                    ->label('Complete')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (MaintenanceRequest $r): bool => $r->isInProgress())
                    ->schema([
                        self::moneyInput('labour', __('Labour (optional)')),
                        self::moneyInput('materials', __('Materials (optional)')),
                        self::moneyInput('cost', __('Total cost (optional)'))
                            ->helperText(__('Whole shillings. Leave empty to use labour plus materials.')),
                        Textarea::make('note')->rows(2)->placeholder('Resolution summary'),
                    ])
                    ->modalHeading('Complete this request?')
                    ->action(function (array $data, MaintenanceRequest $record): void {
                        try {
                            $record->complete(
                                userId: auth()->id(),
                                costCents: $data['cost'] ?? null,
                                note: $data['note'] ?? null,
                                labourCents: $data['labour'] ?? null,
                                materialsCents: $data['materials'] ?? null,
                            );
                            Notification::make()->title('Marked completed')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not complete')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('signOff')
                    ->label(__('Sign off'))
                    ->icon(Heroicon::OutlinedHandThumbUp)
                    ->color('success')
                    ->visible(fn (MaintenanceRequest $r): bool => $r->isCompleted() && ! $r->isSignedOff() && (auth()->user()?->can('update', $r) ?? false))
                    ->schema([
                        Textarea::make('note')
                            ->label(__('Note (optional)'))
                            ->rows(2)
                            ->placeholder(__('e.g. Checked with the tenant, tap no longer leaks')),
                    ])
                    ->modalHeading(__('Sign off the finished work?'))
                    ->modalDescription(__('Confirms the work was checked and accepted.'))
                    ->action(function (array $data, MaintenanceRequest $record): void {
                        try {
                            $record->signOff(auth()->id(), $data['note'] ?? null);
                            Notification::make()->title(__('Signed off'))->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title(__('Could not sign off'))->body($e->getMessage())->danger()->send();
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

    /** Whole shillings in the form, cents out. */
    private static function moneyInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('TZS')
            ->numeric()
            ->minValue(0)
            ->step(1)
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round(((float) $state) * 100));
    }
}
