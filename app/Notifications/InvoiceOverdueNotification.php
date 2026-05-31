<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the renter when DetectOverdueInvoices promotes one of their
 * invoices to `overdue`. Idempotent at the DB level — Invoice::markOverdueIfDue
 * only triggers once per invoice, so this notification fires once per
 * overdue transition.
 *
 * Audience: renter on the lease. Database + mail.
 */
class InvoiceOverdueNotification extends Notification
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
            'type' => 'invoice_overdue',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'balance' => $this->invoice->formatted_balance ?? null,
            'due_date' => $this->invoice->due_date?->toDateString(),
            'url' => $this->portalUrl(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = Client::find($this->invoice->tenant_id);
        $clientName = $client?->name ?? 'PMS';

        return (new MailMessage)
            ->subject(__('Overdue: invoice :number', ['number' => $this->invoice->invoice_number ?? '—']))
            ->greeting(__('Hello,'))
            ->line(__('Your invoice :number is now past due.', ['number' => $this->invoice->invoice_number ?? '—']))
            ->line(__('Balance: :amount', ['amount' => $this->invoice->formatted_balance ?? '—']))
            ->line(__('Was due: :date', ['date' => $this->invoice->due_date?->format('d/m/Y') ?? '—']))
            ->action(__('View in the portal'), $this->portalUrl())
            ->line(__('If you have already paid, please contact :client to confirm.', ['client' => $clientName]));
    }

    private function portalUrl(): string
    {
        $client = Client::find($this->invoice->tenant_id);

        return url('/'.($client?->slug ?? '').'/portal/invoices');
    }
}
