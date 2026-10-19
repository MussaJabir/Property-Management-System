<?php

namespace App\Filament\Operator\Resources\Invoices\RelationManagers;

use App\Models\Invoice;
use App\Models\PaymentRequest;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only history of mobile money prompts sent for an invoice through
 * Selcom: when, to which number, and what came of it. Only shown once a
 * prompt has been sent.
 */
class PaymentRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentRequests';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Mobile money requests');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Invoice && $ownerRecord->paymentRequests()->exists();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Sent'))
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('msisdn')
                    ->label(__('Phone'))
                    ->formatStateUsing(fn (string $state): string => '+'.$state),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn (PaymentRequest $record): string => $record->currency.' '.number_format($record->amount / 100, 0, '.', ',')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PaymentRequest::STATUS_PENDING => __('Waiting for payer'),
                        PaymentRequest::STATUS_COMPLETED => __('Paid'),
                        PaymentRequest::STATUS_CANCELLED => __('Cancelled'),
                        PaymentRequest::STATUS_EXPIRED => __('Expired'),
                        default => __('Failed'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        PaymentRequest::STATUS_PENDING => 'warning',
                        PaymentRequest::STATUS_COMPLETED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('failure_reason')
                    ->label(__('Note'))
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }
}
