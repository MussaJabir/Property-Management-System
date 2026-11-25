<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the renter a few days before an unpaid invoice is due. Renters with
 * a portal account also see it in the portal; others get the email only.
 */
class RentDueReminderNotification extends Notification
{
    public function __construct(public Invoice $invoice) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'rent_due_soon',
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

        return (new MailMessage)
            ->subject(__('Rent due on :date', ['date' => $this->invoice->due_date?->format('d/m/Y') ?? '—']))
            ->greeting(__('Hello,'))
            ->line(__('A reminder that invoice :number is due on :date.', [
                'number' => $this->invoice->invoice_number ?? '—',
                'date' => $this->invoice->due_date?->format('d/m/Y') ?? '—',
            ]))
            ->line(__('Amount to pay: :amount', ['amount' => $this->invoice->formatted_balance ?? '—']))
            ->action(__('View in the portal'), $this->portalUrl())
            ->line(__('If you have already paid, thank you. :client', ['client' => $client?->name ?? '']));
    }

    private function portalUrl(): string
    {
        $client = Client::find($this->invoice->tenant_id);

        return url('/'.($client?->slug ?? '').'/portal/invoices');
    }
}
