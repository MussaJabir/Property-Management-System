<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ContactSubmission;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every active operator on the client when a public contact-form
 * submission lands. Sync (no Horizon until Phase 11); ContactForm wraps
 * dispatch in try/catch so a flaky SMTP can't lose the submission.
 */
class ContactSubmissionReceivedNotification extends Notification
{
    public function __construct(public ContactSubmission $submission) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->submission;

        $mail = (new MailMessage)
            ->subject(__('New contact form submission'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__(':name has sent you a message via your public contact form.', ['name' => $s->name]));

        if ($s->email) {
            $mail->line(__('Email: :email', ['email' => $s->email]));
        }
        if ($s->phone) {
            $mail->line(__('Phone: :phone', ['phone' => $s->phone]));
        }

        return $mail
            ->line('—')
            ->line($s->message)
            ->line('—')
            ->line(__('Reply directly to this person or open the submission in your inbox.'))
            ->action(__('Open submission'), url('/manage/contact-submissions/'.$s->id.'/edit'));
    }
}
