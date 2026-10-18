<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FeedbackReport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the BJP team (super admins) that a client sent feedback.
 */
class FeedbackReceivedNotification extends Notification
{
    public function __construct(public FeedbackReport $report) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = $this->report->client;
        $type = FeedbackReport::typeLabels()[$this->report->type] ?? $this->report->type;

        return (new MailMessage)
            ->subject(__(':type from :client', ['type' => $type, 'client' => $client !== null ? $client->name : '—']))
            ->greeting(__('New feedback from a client.'))
            ->line(__('From :name (:email) at :client.', [
                'name' => $this->report->user !== null ? $this->report->user->name : '—',
                'email' => $this->report->user !== null ? $this->report->user->email : '—',
                'client' => $client !== null ? $client->name : '—',
            ]))
            ->line('“'.Str::limit($this->report->message, 600).'”')
            ->line($this->report->page_url !== null ? __('Page: :url', ['url' => $this->report->page_url]) : '')
            ->action(__('Open feedback'), url('/admin/feedback-reports/'.$this->report->getKey().'/edit'));
    }
}
