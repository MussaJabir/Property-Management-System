<?php

namespace App\Filament\Operator\Resources\Payments\Pages;

use App\Filament\Operator\Resources\Payments\PaymentResource;
use App\Filament\Operator\Resources\Receipts\ReceiptResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receipts')
                ->label(__('Receipts'))
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color('gray')
                ->visible(fn (): bool => ReceiptResource::canAccess())
                ->url(fn (): string => ReceiptResource::getUrl()),
            CreateAction::make()->label('Record payment'),
        ];
    }
}
