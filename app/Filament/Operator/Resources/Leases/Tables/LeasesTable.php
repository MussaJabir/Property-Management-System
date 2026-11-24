<?php

namespace App\Filament\Operator\Resources\Leases\Tables;

use App\Filament\Operator\Resources\Leases\LeaseActions;
use App\Http\Controllers\Rating\LeaseRatingController;
use App\Models\Lease;
use App\Services\LeasePdfGenerator;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
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

                LeaseActions::activate(),
                LeaseActions::renew(),
                LeaseActions::end(),
                LeaseActions::terminate(),

                Action::make('askForReview')
                    ->label(__('Ask for a review'))
                    ->icon(Heroicon::OutlinedStar)
                    ->color('gray')
                    ->visible(fn (Lease $record): bool => $record->isActive() && LeaseRatingController::whatsAppUrl($record) !== null)
                    ->url(fn (Lease $record): ?string => LeaseRatingController::whatsAppUrl($record), shouldOpenInNewTab: true),

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
