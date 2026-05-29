<?php

namespace App\Filament\Operator\Resources\Receipts\Tables;

use App\Models\Receipt;
use App\Notifications\ReceiptIssuedNotification;
use App\Services\ReceiptPdfGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Notification as Notifier;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Number')
                    ->searchable()
                    ->weight('semibold')
                    ->sortable(),

                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('payment.invoice.lease.renter.display_name')
                    ->label('Renter')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('payment.invoice.lease.renter', function ($q) use ($search) {
                            $q->where('full_name', 'ilike', "%{$search}%")
                                ->orWhere('business_name', 'ilike', "%{$search}%");
                        });
                    }),

                TextColumn::make('payment.formatted_amount')
                    ->label('Amount'),

                TextColumn::make('payment.invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('sent_via_email_at')
                    ->label('Emailed')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->color('gray')
                    ->action(function (Receipt $record): StreamedResponse {
                        try {
                            $bytes = app(ReceiptPdfGenerator::class)->render($record);
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not generate PDF')->body($e->getMessage())->danger()->send();

                            return response()->streamDownload(fn () => print (''), 'error.txt');
                        }

                        return response()->streamDownload(
                            fn () => print ($bytes),
                            $record->receipt_number.'.pdf',
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),

                Action::make('email')
                    ->label('Email to renter')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Email this receipt to the renter?')
                    ->modalDescription(function (Receipt $record): string {
                        $renter = $record->payment?->invoice?->lease?->renter;

                        return 'Sends to: '.($renter && $renter->email ? $renter->email : '(no email on file)');
                    })
                    ->visible(fn (Receipt $record): bool => filled($record->payment?->invoice?->lease?->renter?->email))
                    ->action(function (Receipt $record): void {
                        $renter = $record->payment?->invoice?->lease?->renter;
                        if (! $renter?->email) {
                            Notification::make()->title('No email on file')->warning()->send();

                            return;
                        }

                        try {
                            Notifier::route('mail', $renter->email)
                                ->notify(new ReceiptIssuedNotification($record));

                            Notification::make()->title('Receipt emailed')->body($renter->email)->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not send')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('issued_at', 'desc');
    }
}
