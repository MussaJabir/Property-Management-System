<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\PaymentRequestRefused;
use App\Exceptions\SelcomUnavailable;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\SelcomAccount;
use App\Services\Payments\Selcom\SelcomClient;
use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Asks the payer for an invoice's balance by mobile money through the
 * workspace's own Selcom account: creates a Selcom order, then sends the
 * push USSD prompt to their phone, where they confirm with their PIN.
 *
 * The request stays pending here; Selcom's webhook and the status checks
 * (next PRs) record the payment once Selcom confirms it.
 *
 * Callers must run inside the invoice's tenancy.
 */
class PaymentRequester
{
    /** Don't send a second prompt for the same invoice within this time. */
    public const RESEND_AFTER_MINUTES = 2;

    /** Selcom order expiry, in minutes. */
    public const ORDER_EXPIRY_MINUTES = 60;

    /** Is Selcom set up and switched on for this workspace? */
    public static function available(): bool
    {
        $client = tenant();

        if (! $client instanceof Client) {
            return false;
        }

        return SelcomAccount::forClient($client)?->isReady() ?? false;
    }

    /** Can this invoice be paid by mobile money right now? */
    public static function canRequest(Invoice $invoice): bool
    {
        return $invoice->isOutstanding()
            && $invoice->currency === 'TZS'
            && intdiv($invoice->balanceDue(), 100) > 0
            && self::available();
    }

    /**
     * Ask for the full balance of $invoice from $phone.
     *
     * @throws PaymentRequestRefused with a message to show
     */
    public function request(Invoice $invoice, string $phone, ?int $requestedBy = null): PaymentRequest
    {
        $client = tenant();
        $account = $client instanceof Client ? SelcomAccount::forClient($client) : null;

        if ($account === null || ! $account->isReady()) {
            throw new PaymentRequestRefused(__('Mobile money payments are not switched on for this workspace.'));
        }

        $invoice->refresh();

        if (! $invoice->isOutstanding()) {
            throw new PaymentRequestRefused(__('This invoice has nothing left to pay.'));
        }

        if ($invoice->currency !== 'TZS') {
            throw new PaymentRequestRefused(__('Mobile money payments are only for TZS invoices.'));
        }

        // Selcom takes whole shillings.
        $shillings = intdiv($invoice->balanceDue(), 100);

        if ($shillings < 1) {
            throw new PaymentRequestRefused(__('This invoice has nothing left to pay.'));
        }

        $msisdn = self::msisdn($phone);

        $recent = PaymentRequest::query()
            ->where('invoice_id', $invoice->id)
            ->pending()
            ->where('created_at', '>=', now()->subMinutes(self::RESEND_AFTER_MINUTES))
            ->exists();

        if ($recent) {
            throw new PaymentRequestRefused(__('A payment prompt was just sent for this invoice. Wait a moment for it on the phone before sending another.'));
        }

        $request = PaymentRequest::create([
            'invoice_id' => $invoice->id,
            'amount' => $shillings * 100,
            'currency' => 'TZS',
            'msisdn' => $msisdn,
            'status' => PaymentRequest::STATUS_PENDING,
            'requested_by_user_id' => $requestedBy,
        ]);

        try {
            $selcom = SelcomClient::for($account);

            $order = $selcom->createOrderMinimal($this->order($invoice, $request, $shillings, $client));
        } catch (SelcomUnavailable $e) {
            $this->fail($request, $e->getMessage());

            throw new PaymentRequestRefused($e->getMessage(), previous: $e);
        }

        if (! $order->successful()) {
            $this->fail($request, trim($order->resultCode.' '.$order->message));

            throw new PaymentRequestRefused(__('Selcom did not accept the payment request: :message', ['message' => $order->message ?: $order->resultCode]));
        }

        try {
            $push = $selcom->walletPayment($request->transid, $request->order_id, $msisdn);
        } catch (SelcomUnavailable $e) {
            // Selcom may have sent the prompt before the connection
            // dropped, so leave it pending for the status checks.
            Log::warning('Selcom wallet-payment unreachable', ['order_id' => $request->order_id, 'error' => $e->getMessage()]);

            return $request;
        }

        if ($push->failed()) {
            $this->fail($request, trim($push->resultCode.' '.$push->message));

            throw new PaymentRequestRefused(__('The payment prompt could not be sent: :message', ['message' => $push->message ?: $push->resultCode]));
        }

        return $request;
    }

    /**
     * A Tanzanian number as Selcom wants it: 255XXXXXXXXX.
     *
     * @throws PaymentRequestRefused
     */
    public static function msisdn(string $phone): string
    {
        try {
            $number = new PhoneNumber(trim($phone), 'TZ');

            if (! $number->isValid() || ! $number->isOfCountry('TZ')) {
                throw new PaymentRequestRefused(__('Enter a valid Tanzanian mobile number.'));
            }

            return ltrim($number->formatE164(), '+');
        } catch (PaymentRequestRefused $e) {
            throw $e;
        } catch (Throwable) {
            throw new PaymentRequestRefused(__('Enter a valid Tanzanian mobile number.'));
        }
    }

    /** The payer's phone on file, if any, for prefilling. */
    public static function payerPhone(Invoice $invoice): ?string
    {
        $phone = $invoice->lease->renter->phone ?? $invoice->booking->guest->phone ?? null;

        // The E.164 cast hands back a PhoneNumber object.
        return $phone !== null ? (string) $phone : null;
    }

    /** @return array<string, string|int> */
    private function order(Invoice $invoice, PaymentRequest $request, int $shillings, Client $client): array
    {
        $label = $invoice->invoice_number ?? __('Invoice');

        // Selcom requires an email; many renters and guests have none.
        $email = $invoice->lease->renter->email
            ?? $invoice->booking->guest->email
            ?? $client->contact_email
            ?? config('mail.from.address');

        return [
            'order_id' => $request->order_id,
            'buyer_email' => (string) $email,
            'buyer_name' => mb_substr($invoice->bill_to_name, 0, 100),
            'buyer_phone' => $request->msisdn,
            'amount' => $shillings,
            'currency' => 'TZS',
            'buyer_remarks' => $label,
            'merchant_remarks' => $label,
            'no_of_items' => 1,
            'expiry' => self::ORDER_EXPIRY_MINUTES,
        ];
    }

    private function fail(PaymentRequest $request, string $reason): void
    {
        $request->forceFill([
            'status' => PaymentRequest::STATUS_FAILED,
            'failure_reason' => mb_substr($reason, 0, 255),
        ])->save();
    }
}
