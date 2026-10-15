<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the BJP team that a client hit a plan limit and asked to upgrade.
 * Sent to super admins; payment and the plan switch are handled by hand.
 */
class PlanUpgradeRequestedNotification extends Notification
{
    public function __construct(
        public Client $client,
        public User $requestedBy,
        public string $usageLine,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Upgrade requested: :client', ['client' => $this->client->name]))
            ->greeting(__('A client wants a bigger plan.'))
            ->line(__(':client has reached a limit on its current plan.', ['client' => $this->client->name]))
            ->line($this->usageLine)
            ->line(__('Requested by :name (:email).', ['name' => $this->requestedBy->name, 'email' => $this->requestedBy->email]))
            ->action(__('Open clients'), url('/admin/clients'))
            ->line(__('Agree the new plan, collect payment, then change their plan in the admin panel.'));
    }
}
