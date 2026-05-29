<?php

namespace App\Notifications;

use App\Models\Receipt;
use App\Services\ReceiptPdfGenerator;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to a renter (or any notifiable with an email) when a
 * receipt is issued. The PDF is generated lazily and attached to the email.
 *
 * Mail driver:
 *   - dev: SMTP → Mailpit (see SETUP.md)
 *   - prod: switch MAIL_MAILER=resend (or use Resend SMTP) — no code change
 *
 * Not currently queued (no Horizon worker provisioned yet — Phase 11). Send
 * is synchronous which adds ~2s for the Browsershot PDF render. PaymentObserver
 * already wraps the dispatch in try/catch so a flaky SMTP can't break payment
 * recording. Re-enable ShouldQueue once a worker container ships.
 */
class ReceiptIssuedNotification extends Notification
{
    public function __construct(public Receipt $receipt) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->receipt->loadMissing(['payment.invoice.lease.renter', 'payment.invoice.lease.unit.property']);
        $payment = $this->receipt->payment;
        $invoice = $payment?->invoice;

        $renter = $invoice?->lease?->renter;
        $renterName = $renter ? $renter->display_name : __('Renter');
        $amount = $payment ? $payment->formatted_amount : '';
        $invoiceNumber = $invoice ? (string) $invoice->invoice_number : '';

        $pdfBytes = app(ReceiptPdfGenerator::class)->render($this->receipt);

        $mail = (new MailMessage)
            ->subject(__('Receipt :number — :amount', ['number' => $this->receipt->receipt_number, 'amount' => $amount]))
            ->greeting(__('Hello :name,', ['name' => $renterName]))
            ->line(__('We have received your payment. The receipt is attached for your records.'))
            ->line(__('Receipt number: :n', ['n' => $this->receipt->receipt_number]))
            ->line(__('Amount: :a', ['a' => $amount]));

        if ($invoiceNumber) {
            $mail->line(__('Against invoice: :i', ['i' => $invoiceNumber]));
        }

        $mail->salutation(__('Thank you.'))
            ->attachData(
                $pdfBytes,
                $this->receipt->receipt_number.'.pdf',
                ['mime' => 'application/pdf'],
            );

        // Stamp the email-sent timestamp once the message is built.
        // (Idempotent — same field updated on retries.)
        $this->receipt->forceFill(['sent_via_email_at' => now()])->save();

        return $mail;
    }
}
