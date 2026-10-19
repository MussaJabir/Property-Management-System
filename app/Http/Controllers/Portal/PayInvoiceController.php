<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Exceptions\PaymentRequestRefused;
use App\Models\Invoice;
use App\Services\Payments\PaymentRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renter portal "Pay by mobile money": sends the Selcom prompt for one of
 * the renter's own invoices to the number they give. A plain form post,
 * because portal Livewire calls run outside tenancy.
 */
class PayInvoiceController
{
    public function __invoke(Request $request, string $invoice): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $renter = Auth::guard('renter')->user()?->renter;
        $record = Invoice::query()->find($invoice);

        // Same workspace is not enough: only the renter's own invoices.
        if ($renter === null || $record === null || $record->lease?->renter_id !== $renter->id) {
            throw new NotFoundHttpException;
        }

        $back = redirect()->route('portal.invoices.index', ['tenant' => tenant('id')]);

        try {
            $sent = app(PaymentRequester::class)->request($record, $data['phone']);
        } catch (PaymentRequestRefused $e) {
            return $back->withErrors(['pay' => $e->getMessage()])->withInput();
        }

        return $back->with('status', __('Check your phone (+:phone) and enter your PIN to pay :amount. Your receipt will appear here once the payment goes through.', [
            'phone' => $sent->msisdn,
            'amount' => 'TZS '.number_format($sent->amount / 100, 0, '.', ','),
        ]));
    }
}
