<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\MaintenanceRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to every operator on the client with role=manager or
 * role=maintenance-staff when a renter (or operator) submits a new
 * maintenance request via the portal or operator panel.
 *
 * Audience: all relevant operators. Database + mail so the bell badges
 * immediately even if SMTP is slow.
 */
class MaintenanceRequestSubmittedNotification extends Notification
{
    public function __construct(public MaintenanceRequest $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $client = Client::find($this->request->tenant_id);

        return [
            'type' => 'maintenance_submitted',
            'request_id' => $this->request->id,
            'title' => $this->request->title,
            'priority' => $this->request->priority,
            'unit' => $this->request->unit?->code,
            'url' => url('/manage/maintenance-requests/'.$this->request->id.'/edit'),
            'client_slug' => $client?->slug,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = Client::find($this->request->tenant_id);
        $unitCode = $this->request->unit?->code ?? '—';
        $propertyName = $this->request->unit?->property?->name ?? '';

        return (new MailMessage)
            ->subject(__('[:priority] New maintenance request: :title', [
                'priority' => Str::upper($this->request->priority ?? 'normal'),
                'title' => $this->request->title,
            ]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('A new maintenance request has been raised on :unit (:property).', [
                'unit' => $unitCode,
                'property' => $propertyName,
            ]))
            ->line(__('Priority: :priority', ['priority' => ucfirst($this->request->priority ?? 'normal')]))
            ->line(__('Title: :title', ['title' => $this->request->title]))
            ->line('—')
            ->line($this->request->description ?? '')
            ->action(__('Open in the panel'), url('/manage/maintenance-requests/'.$this->request->id.'/edit'));
    }
}
