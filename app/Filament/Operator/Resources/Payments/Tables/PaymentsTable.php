<?php

namespace App\Filament\Operator\Resources\Payments\Tables;

use App\Models\Payment;
use App\Services\Payments\PaymentAllocator;
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
use Throwable;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('payer')
                    ->label('Renter')
                    ->getStateUsing(fn (Payment $record): ?string => $record->renter !== null ? $record->renter->display_name : $record->invoice?->bill_to_name)
                    ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q
                        ->whereHas('renter', fn ($r) => $r->where('full_name', 'ilike', "%{$search}%")->orWhere('business_name', 'ilike', "%{$search}%"))
                        ->orWhereHas('invoice', fn ($i) => $i->billToMatches($search)))),

                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->sortable(query: fn ($q, string $d) => $q->orderBy('amount', $d)),

                TextColumn::make('method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state, Payment $record): string => $record->method_label),

                // The M-Pesa / bank code staff look payments up by.
                TextColumn::make('reference_number')
                    ->label('Transaction ID')
                    ->getStateUsing(fn (Payment $record): ?string => $record->transaction_id ?: $record->reference_number)
                    ->searchable(['transaction_id', 'reference_number'])
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('receipt.receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        'reversed' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (Payment $record): ?string => $record->isReversed() ? $record->reversal_reason : null),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->options([
                        Payment::METHOD_CASH => 'Cash',
                        Payment::METHOD_BANK_TRANSFER => 'Bank transfer',
                        Payment::METHOD_MOBILE_MONEY => 'Mobile money',
                        Payment::METHOD_CHEQUE => 'Cheque',
                        Payment::METHOD_CARD => 'Card',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        Payment::STATUS_PENDING => 'Pending',
                        Payment::STATUS_COMPLETED => 'Completed',
                        Payment::STATUS_FAILED => 'Failed',
                        Payment::STATUS_REFUNDED => 'Refunded',
                        Payment::STATUS_REVERSED => 'Reversed',
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('reverse')
                    ->label(__('Reverse'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->visible(fn (Payment $record): bool => auth()->user()?->can('reverse', $record) ?? false)
                    ->modalHeading(__('Reverse this payment?'))
                    ->modalDescription(fn (Payment $record): string => __(':amount goes back to being owed. The payment and its receipt stay on record, marked reversed.', ['amount' => $record->formatted_amount]))
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('Why?'))
                            ->placeholder(__('e.g. Cheque bounced, recorded twice'))
                            ->required()
                            ->maxLength(500)
                            ->rows(2),
                    ])
                    ->modalSubmitActionLabel(__('Reverse payment'))
                    ->action(function (Payment $record, array $data): void {
                        try {
                            app(PaymentAllocator::class)->reverse($record, (string) $data['reason'], auth()->id());
                            Notification::make()->success()->title(__('Payment reversed'))->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title(__('Could not reverse'))->body($e->getMessage())->send();
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
            ->defaultSort('payment_date', 'desc');
    }
}
