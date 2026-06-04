<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a freshly-provisioned renter to activate their portal account by
 * setting their own password through a one-time, expiring link. No password
 * is ever transmitted (see SECURITY: renter account takeover).
 *
 * Delivered by email when the renter has an address on file; the operator can
 * also copy/share the same link via the "resend activation" action.
 *
 * Synchronous (no Horizon yet — Phase 11). The caller wraps dispatch in
 * try/catch so a flaky SMTP can't break lease activation.
 */
class PortalActivationNotification extends Notification
{
    public function __construct(public string $activationUrl) {}

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

        return (new MailMessage)
            ->subject(__('Activate your :app portal account', ['app' => $clientName]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':client has set up an online portal where you can view your lease, invoices, receipts and submit maintenance requests.', ['client' => $clientName]))
            ->line(__('Click the button below to choose your password and activate your account.'))
            ->action(__('Activate my account'), $this->activationUrl)
            ->line(__('This link expires in 72 hours. If it has expired, contact :client for a new one.', ['client' => $clientName]));
    }
}
