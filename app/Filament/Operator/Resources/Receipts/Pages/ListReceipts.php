<?php

namespace App\Filament\Operator\Resources\Receipts\Pages;

use App\Filament\Operator\Resources\Receipts\ReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListReceipts extends ListRecords
{
    protected static string $resource = ReceiptResource::class;

    protected function getHeaderActions(): array
    {
        // No create — receipts are issued automatically by the Payment observer.
        return [];
    }
}
