<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Receipt;
use App\Notifications\ReceiptIssuedNotification;
use App\Services\ReceiptNumberGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Keeps the invoice's amount_paid / status in sync with the live payments
 * table, and issues a Receipt the first time a payment reaches `completed`.
 *
 * Idempotent on every event:
 *   - status recompute is a pure function of completed payments → safe to
 *     run repeatedly
 *   - receipt creation is gated on "no existing receipt for this payment"
 */
class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->syncInvoice($payment);
        $this->maybeIssueReceipt($payment);
    }

    public function updated(Payment $payment): void
    {
        $this->syncInvoice($payment);
        $this->maybeIssueReceipt($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->syncInvoice($payment);
    }

    public function restored(Payment $payment): void
    {
        $this->syncInvoice($payment);
        $this->maybeIssueReceipt($payment);
    }

    protected function syncInvoice(Payment $payment): void
    {
        $payment->invoice?->recomputeStatus();
    }

    protected function maybeIssueReceipt(Payment $payment): void
    {
        if (! $payment->isCompleted()) {
            return;
        }

        // One receipt per payment — even if the payment row gets toggled
        // through statuses we never write a duplicate.
        if ($payment->receipt()->exists()) {
            return;
        }

        $receipt = Receipt::create([
            'tenant_id' => $payment->tenant_id,
            'payment_id' => $payment->id,
            'receipt_number' => app(ReceiptNumberGenerator::class)->next($payment->tenant_id),
            'issued_at' => now(),
        ]);

        $this->maybeEmail($payment, $receipt);
    }

    /**
     * Best-effort send to the renter's email. Failure is logged but never
     * blocks payment recording — operators record cash payments offline and
     * we don't want a flaky SMTP server to break that flow.
     */
    protected function maybeEmail(Payment $payment, Receipt $receipt): void
    {
        $renter = $payment->invoice?->lease?->renter;
        if (! $renter?->email) {
            return;
        }

        try {
            Notification::route('mail', $renter->email)
                ->notify(new ReceiptIssuedNotification($receipt));
        } catch (Throwable $e) {
            Log::warning('Failed to send receipt email', [
                'receipt_id' => $receipt->id,
                'renter_email' => $renter->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
