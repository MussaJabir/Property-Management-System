<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome email sent to the first operator (owner) user when a super admin
 * provisions a new client workspace. Carries a one-time temporary password
 * the user is forced to change on first sign-in.
 *
 * Sync (no Horizon yet — Phase 11). Caller wraps dispatch in try/catch.
 */
class OperatorCredentialsIssuedNotification extends Notification
{
    public function __construct(public string $temporaryPassword) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $client = $notifiable->client ?? Client::find($notifiable->tenant_id);
        $clientName = $client?->name ?? 'PMS';
        $loginUrl = url('/manage/login');

        return (new MailMessage)
            ->subject(__('Your :app workspace is ready', ['app' => $clientName]))
            ->greeting(__('Welcome, :name', ['name' => $notifiable->name]))
            ->line(__('Your :app workspace has been set up. You can now sign in to manage your properties, renters, leases and invoices.', ['app' => $clientName]))
            ->line(__('Sign-in details:'))
            ->line(__('Email: :email', ['email' => $notifiable->email]))
            ->line(__('Temporary password: :password', ['password' => $this->temporaryPassword]))
            ->action(__('Open the workspace'), $loginUrl)
            ->line(__('You will be asked to choose a new password the first time you sign in.'));
    }
}
