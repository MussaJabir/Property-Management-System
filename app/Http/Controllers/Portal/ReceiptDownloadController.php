<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\ReceiptPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $bytes = app(ReceiptPdfGenerator::class)->render($receipt);

        return response()->streamDownload(
            fn () => print ($bytes),
            'receipt-'.$receipt->receipt_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
