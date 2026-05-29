<?php

namespace App\Filament\Operator\Resources\Invoices\Pages;

use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // New invoices always start in draft. Status is governed by issue() /
        // cancel() actions and the Payment observer, never by the form.
        $data['status'] = Invoice::STATUS_DRAFT;

        return $data;
    }
}
