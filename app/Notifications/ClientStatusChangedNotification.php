<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a client's owner when their workspace is suspended or reactivated by
 * the BJP team. Suspension is usually for an unpaid subscription, so the
 * message points them at support to restore access.
 *
 * Sent synchronously from ClientObserver on a status change.
 */
class ClientStatusChangedNotification extends Notification
{
    public function __construct(
        public Client $client,
        public bool $suspended,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->client->name;
        $loginUrl = url('/manage/login');

        if ($this->suspended) {
            return (new MailMessage)
                ->subject(__('Your :app workspace has been suspended', ['app' => $name]))
                ->greeting(__('Hello'))
                ->line(__('Your :app workspace on PMS has been temporarily suspended.', ['app' => $name]))
                ->line(__('This usually happens when a subscription needs to be renewed. While suspended, you and your renters cannot sign in.'))
                ->line(__('Please contact the BJP Technologies team to restore access.'))
                ->salutation(__('— The PMS team at BJP Technologies'));
        }

        return (new MailMessage)
            ->subject(__('Your :app workspace is active again', ['app' => $name]))
            ->greeting(__('Good news'))
            ->line(__('Your :app workspace on PMS has been reactivated. You can sign in and pick up right where you left off.', ['app' => $name]))
            ->action(__('Sign in'), $loginUrl)
            ->salutation(__('— The PMS team at BJP Technologies'));
    }
}
