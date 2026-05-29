<?php

namespace App\Filament\Operator\Resources\Leases\Tables;

use App\Models\Lease;
use App\Services\LeasePdfGenerator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LeasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('renter.display_name')
                    ->label('Renter')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('renter', function ($q) use ($search) {
                            $q->where('full_name', 'ilike', "%{$search}%")
                                ->orWhere('business_name', 'ilike', "%{$search}%");
                        });
                    })
                    ->weight('semibold'),

                TextColumn::make('unit.code')
                    ->label('Unit')
                    ->description(fn (Lease $record): ?string => $record->unit?->property?->name)
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End')
                    ->date('d/m/Y')
                    ->placeholder('Open-ended')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('formatted_rent')
                    ->label('Rent')
                    ->sortable(query: fn ($q, string $d) => $q->orderBy('rent_amount', $d)),

                TextColumn::make('billing_cycle_label')
                    ->label('Cycle')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'ended' => 'gray',
                        'terminated' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'ended' => 'Ended',
                        'terminated' => 'Terminated',
                    ]),
                SelectFilter::make('billing_cycle')
                    ->label('Billing cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'semi_annual' => 'Semi-annual',
                        'annual' => 'Yearly',
                        'custom' => 'Custom',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('history')
                    ->label('History')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->modalHeading(function (Lease $record): string {
                        $who = $record->renter ? $record->renter->display_name : 'lease';
                        $unit = $record->unit ? $record->unit->code : '—';

                        return "Lease history — {$who} / {$unit}";
                    })
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (Lease $record) => view('leases.history-modal', [
                        'history' => $record->history()->with('user')->get(),
                    ])),

                Action::make('activate')
                    ->label('Activate')
                    ->icon(Heroicon::OutlinedPlayCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activate this lease?')
                    ->modalDescription('The linked unit will be marked occupied.')
                    ->visible(fn (Lease $record): bool => $record->isPending())
                    ->action(function (Lease $record) {
                        try {
                            $record->activate(auth()->id());
                            Notification::make()->title('Lease activated')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not activate')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('end')
                    ->label('End (natural)')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Mark this lease as ended?')
                    ->modalDescription('Use this when the lease reaches its agreed end date. The unit becomes vacant.')
                    ->visible(fn (Lease $record): bool => $record->isActive())
                    ->action(function (Lease $record) {
                        try {
                            $record->end(auth()->id());
                            Notification::make()->title('Lease ended')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not end lease')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('terminate')
                    ->label('Terminate')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Lease $record): bool => $record->isActive())
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Why is this lease being terminated early?'),
                    ])
                    ->modalHeading('Terminate this lease early?')
                    ->modalDescription('Recorded in lease history. The unit becomes vacant.')
                    ->action(function (array $data, Lease $record) {
                        try {
                            $record->terminate($data['reason'] ?? null, auth()->id());
                            Notification::make()->title('Lease terminated')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not terminate')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->action(function (Lease $record): StreamedResponse {
                        try {
                            $bytes = app(LeasePdfGenerator::class)->render($record);
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Could not generate PDF')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            // Empty stream so Filament doesn't bubble an unhandled exception.
                            return response()->streamDownload(fn () => print (''), 'error.txt');
                        }

                        $filename = 'lease-'.substr($record->id, 0, 8).'.pdf';

                        return response()->streamDownload(fn () => print ($bytes), $filename, [
                            'Content-Type' => 'application/pdf',
                        ]);
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
            ->defaultSort('start_date', 'desc');
    }
}
