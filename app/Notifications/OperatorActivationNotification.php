<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites an operator (staff/owner) to activate their account by setting their
 * own password through a one-time, expiring link. No password is transmitted.
 *
 * Synchronous (no Horizon yet — Phase 11); the caller wraps dispatch in
 * try/catch so a flaky SMTP can't break provisioning.
 */
class OperatorActivationNotification extends Notification
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
        $clientName = $client?->name ?? 'PMS';

        return (new MailMessage)
            ->subject(__('Activate your :app staff account', ['app' => $clientName]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You have been given access to the :client workspace on PMS.', ['client' => $clientName]))
            ->line(__('Click the button below to choose your password and activate your account.'))
            ->action(__('Activate my account'), $this->activationUrl)
            ->line(__('This link expires in 72 hours. If it expires, ask an administrator to resend your invite.'));
    }
}
