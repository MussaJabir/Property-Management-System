<?php

namespace App\Filament\Operator\Resources\Payments\Tables;

use App\Models\Payment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

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

                TextColumn::make('invoice.lease.renter.display_name')
                    ->label('Renter')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('invoice.lease.renter', function ($q) use ($search) {
                            $q->where('full_name', 'ilike', "%{$search}%")
                                ->orWhere('business_name', 'ilike', "%{$search}%");
                        });
                    }),

                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->sortable(query: fn ($q, string $d) => $q->orderBy('amount', $d)),

                TextColumn::make('method')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),

                TextColumn::make('reference_number')
                    ->label('Reference')
                    ->searchable()
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
                        default => 'gray',
                    }),
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
                    ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
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
