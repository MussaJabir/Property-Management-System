<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a renter to activate their portal account by setting their own
 * password through a one-time, expiring link. No password is transmitted.
 *
 * Self-contained (renter name + client name + URL are passed in) so it can be
 * delivered as an on-demand notification routed straight to the renter's email
 * address. The portal User row may not carry that email — the users table
 * enforces a platform-wide unique email and a renter can share an address with
 * an operator — so we never rely on the notifiable's own routing here.
 */
class PortalActivationNotification extends Notification
{
    public function __construct(
        public string $activationUrl,
        public string $renterName,
        public string $clientName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Activate your :app portal account', ['app' => $this->clientName]))
            ->greeting(__('Hello :name,', ['name' => $this->renterName]))
            ->line(__(':client has set up an online portal where you can view your lease, invoices, receipts and submit maintenance requests.', ['client' => $this->clientName]))
            ->line(__('Click the button below to choose your password and activate your account.'))
            ->action(__('Activate my account'), $this->activationUrl)
            ->line(__('This link expires in 72 hours. If it has expired, contact :client for a new one.', ['client' => $this->clientName]));
    }
}
