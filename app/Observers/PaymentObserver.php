<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Receipt;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ReceiptIssuedNotification;
use App\Services\ReceiptNumberGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

use function Illuminate\Support\defer;

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

        $receipt = new Receipt([
            'payment_id' => $payment->id,
            'receipt_number' => app(ReceiptNumberGenerator::class)->next($payment->tenant_id),
            'issued_at' => now(),
        ]);
        $receipt->tenant_id = $payment->tenant_id;
        $receipt->save();

        $this->maybeEmail($payment, $receipt);
        $this->maybeBellNotify($payment);
    }

    /**
     * Database notification for the renter's portal bell. Cheap and never
     * blocks — wrapped in try/catch.
     */
    protected function maybeBellNotify(Payment $payment): void
    {
        try {
            $renterUser = $payment->invoice?->lease?->renter?->user;
            if ($renterUser) {
                $renterUser->notify(new PaymentReceivedNotification($payment));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to record payment-received bell notification', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort send to the renter's email. Failure is logged but never
     * blocks payment recording — operators record cash payments offline and
     * we don't want a flaky SMTP server to break that flow.
     *
     * Deferred until after the response: the email renders the receipt PDF
     * (headless Chrome, 15s+ on a small server), and the operator should see
     * "Payment recorded" straight away rather than wait for it.
     */
    protected function maybeEmail(Payment $payment, Receipt $receipt): void
    {
        $email = $payment->invoice?->lease?->renter?->email;
        if (! $email) {
            return;
        }

        defer(function () use ($email, $receipt): void {
            try {
                Notification::route('mail', $email)
                    ->notify(new ReceiptIssuedNotification($receipt));
            } catch (Throwable $e) {
                Log::warning('Failed to send receipt email', [
                    'receipt_id' => $receipt->id,
                    'renter_email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
