<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\ReceiptPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Streams a receipt PDF to the renter. Authorises that the receipt belongs
 * to one of the authenticated renter's leases — same tenant scope alone is
 * not enough (a renter from the same client shouldn't see peer receipts).
 */
class ReceiptDownloadController
{
    public function __invoke(Request $request, Invoice $invoice, Receipt $receipt): StreamedResponse
    {
        $user = Auth::guard('renter')->user();
        $renter = $user?->renter;

        if (! $renter || $invoice->lease?->renter_id !== $renter->id || $receipt->payment?->invoice_id !== $invoice->id) {
            throw new NotFoundHttpException;
        }

        // Serve the stored PDF; render (headless Chromium) only once, on first
        // access, then reuse the cached copy on every later download.
        $disk = Storage::disk(config('filesystems.default'));

        if (! $receipt->pdf_path || ! $disk->exists($receipt->pdf_path)) {
            app(ReceiptPdfGenerator::class)->store($receipt);
            $receipt->refresh();
        }

        return $disk->download((string) $receipt->pdf_path, 'receipt-'.$receipt->receipt_number.'.pdf');
    }
}
