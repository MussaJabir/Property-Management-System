<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Renders a receipt as a PDF via Browsershot (headless Chromium).
 *
 * store() persists the PDF onto the configured default disk (B2 in prod,
 * local in dev) and stamps the receipt's pdf_path so future downloads can
 * stream the stored copy directly instead of re-rendering Chromium.
 */
class ReceiptPdfGenerator
{
    public function render(Receipt $receipt): string
    {
        $receipt->loadMissing(['payment.invoice.lease.renter', 'payment.invoice.lease.unit.property', 'payment.receivedBy']);

        $html = View::make('billing.receipt', [
            'receipt' => $receipt,
            'client' => Client::find($receipt->tenant_id),
        ])->render();

        return Browsershot::html($html)
            ->format('A4')
            ->noSandbox()
            ->showBackground()
            ->pdf();
    }

    /**
     * Generate (or regenerate) the PDF and persist it on the default disk.
     * Returns the storage path that gets stamped onto $receipt->pdf_path.
     */
    public function store(Receipt $receipt): string
    {
        $bytes = $this->render($receipt);

        $path = sprintf('receipts/%s/%s.pdf', $receipt->tenant_id, $receipt->receipt_number);

        Storage::disk(config('filesystems.default'))->put($path, $bytes);

        $receipt->pdf_path = $path;
        $receipt->save();

        return $path;
    }
}
