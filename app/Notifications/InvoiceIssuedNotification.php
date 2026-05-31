<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the renter when a new invoice is issued (status flips from `draft`
 * to `unpaid` via Invoice::markIssued() — or when an invoice is created in
 * an issued state directly).
 *
 * Audience: the renter on the lease. Persisted to the database channel for
 * the in-app notification bell on the renter portal.
 */
class InvoiceIssuedNotification extends Notification
{
    public function __construct(public Invoice $invoice) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'invoice_issued',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->invoice->formatted_total ?? null,
            'due_date' => $this->invoice->due_date?->toDateString(),
            'url' => $this->portalUrl(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = Client::find($this->invoice->tenant_id);
        $clientName = $client?->name ?? 'PMS';

        return (new MailMessage)
            ->subject(__('New invoice :number from :client', [
                'number' => $this->invoice->invoice_number ?? '—',
                'client' => $clientName,
            ]))
            ->greeting(__('Hello,'))
            ->line(__(':client has issued a new invoice for your lease.', ['client' => $clientName]))
            ->line(__('Invoice number: :number', ['number' => $this->invoice->invoice_number ?? '—']))
            ->line(__('Amount due: :amount', ['amount' => $this->invoice->formatted_total ?? '—']))
            ->line(__('Due by: :date', ['date' => $this->invoice->due_date?->format('d/m/Y') ?? '—']))
            ->action(__('Open the portal'), $this->portalUrl())
            ->line(__('You can pay your landlord directly (cash, bank transfer, mobile money). They will record the payment and your portal will update with a receipt.'));
    }

    private function portalUrl(): string
    {
        $client = Client::find($this->invoice->tenant_id);

        return url('/'.($client?->slug ?? '').'/portal/invoices');
    }
}
