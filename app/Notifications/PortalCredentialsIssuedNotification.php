<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome email for a freshly-provisioned renter portal account. Carries the
 * one-time default password so the renter can sign in; they are forced to
 * change it on first login (User::$must_change_password).
 *
 * Synchronous (no Horizon yet — Phase 11). The caller wraps dispatch in
 * try/catch so a flaky SMTP can't break Lease::activate().
 */
class PortalCredentialsIssuedNotification extends Notification
{
    public function __construct(public string $defaultPassword) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $client = $notifiable->client ?? Client::find($notifiable->tenant_id);
        $clientName = $client?->name ?? 'your landlord';

        $portalUrl = url('/'.($client?->slug ?? '').'/portal/login');

        return (new MailMessage)
            ->subject(__('Your :app portal access', ['app' => $clientName]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':client has set up an online portal where you can view your lease, invoices, receipts and submit maintenance requests.', ['client' => $clientName]))
            ->line(__('Your sign-in details:'))
            ->line(__('Phone: :phone', ['phone' => $notifiable->phone]))
            ->line(__('Temporary password: :password', ['password' => $this->defaultPassword]))
            ->action(__('Open the portal'), $portalUrl)
            ->line(__('You will be asked to set a new password the first time you sign in.'));
    }
}
