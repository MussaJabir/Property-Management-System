<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Light-weight "we received your payment" notification.
 *
 * This is distinct from ReceiptIssuedNotification (Phase 5): receipts carry
 * the PDF as an attachment and fire once when the receipt is issued. This
 * one is the in-app database notification primarily — short, immediate, and
 * surfaces in the portal's notification bell.
 */
class PaymentReceivedNotification extends Notification
{
    public function __construct(public Payment $payment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $invoice = $this->payment->invoice;
        $client = Client::find($this->payment->tenant_id);

        return [
            'type' => 'payment_received',
            'payment_id' => $this->payment->id,
            'invoice_id' => $invoice?->id,
            'invoice_number' => $invoice?->invoice_number,
            'amount' => 'TZS '.number_format(((int) $this->payment->amount) / 100, 0, '.', ','),
            'paid_at' => $this->payment->payment_date?->toDateString(),
            'url' => url('/'.($client?->slug ?? '').'/portal/invoices'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Payment received')->line('Payment recorded.');
    }
}
