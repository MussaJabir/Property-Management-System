<?php

namespace App\Filament\Operator\Resources\Invoices\Tables;

use App\Models\Invoice;
use App\Services\InvoicePdfGenerator;
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

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Number')
                    ->searchable()
                    ->placeholder('— draft —')
                    ->weight('semibold')
                    ->sortable(),

                TextColumn::make('lease.renter.display_name')
                    ->label('Renter')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('lease.renter', function ($q) use ($search) {
                            $q->where('full_name', 'ilike', "%{$search}%")
                                ->orWhere('business_name', 'ilike', "%{$search}%");
                        });
                    }),

                TextColumn::make('lease.unit.code')
                    ->label('Unit')
                    ->description(fn (Invoice $record): ?string => $record->lease?->unit?->property?->name)
                    ->toggleable(),

                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Invoice $record): ?string => $record->isPastDue() && ! $record->isPaid() ? 'danger' : null),

                TextColumn::make('formatted_total')
                    ->label('Total')
                    ->sortable(query: fn ($q, string $d) => $q->orderBy('total_amount', $d)),

                TextColumn::make('formatted_balance')
                    ->label('Outstanding')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'unpaid' => 'warning',
                        'partial' => 'info',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('issue')
                    ->label('Issue')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Issue this invoice?')
                    ->modalDescription('Assigns a permanent invoice number and makes it visible to the renter.')
                    ->visible(fn (Invoice $record): bool => $record->isDraft())
                    ->action(function (Invoice $record): void {
                        try {
                            $record->issue();
                            Notification::make()->title('Invoice issued')->body($record->invoice_number)->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not issue')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this invoice?')
                    ->modalDescription('A cancelled invoice cannot be reissued. Refunds for any payments must be handled separately.')
                    ->visible(fn (Invoice $record): bool => ! in_array($record->status, ['paid', 'cancelled'], true))
                    ->action(function (Invoice $record): void {
                        try {
                            $record->cancel();
                            Notification::make()->title('Invoice cancelled')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not cancel')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->action(function (Invoice $record): StreamedResponse {
                        try {
                            $bytes = app(InvoicePdfGenerator::class)->render($record);
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not generate PDF')->body($e->getMessage())->danger()->send();

                            return response()->streamDownload(fn () => print (''), 'error.txt');
                        }

                        $filename = ($record->invoice_number ?? 'invoice-draft-'.substr($record->id, 0, 8)).'.pdf';

                        return response()->streamDownload(
                            fn () => print ($bytes),
                            $filename,
                            ['Content-Type' => 'application/pdf'],
                        );
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
            ->defaultSort('created_at', 'desc');
    }
}
