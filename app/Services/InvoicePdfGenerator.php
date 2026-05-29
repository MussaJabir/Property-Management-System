<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Renders an invoice as a PDF via Browsershot (headless Chromium).
 */
class InvoicePdfGenerator
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'lease.renter', 'lease.unit.property']);

        $html = View::make('billing.invoice', [
            'invoice' => $invoice,
            'client' => Client::find($invoice->tenant_id),
        ])->render();

        return Browsershot::html($html)
            ->format('A4')
            ->noSandbox()
            ->showBackground()
            ->pdf();
    }
}
