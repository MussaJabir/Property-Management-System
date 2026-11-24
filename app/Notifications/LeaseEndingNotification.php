<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Models\Client;
use App\Models\Lease;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A lease ends soon. Staff hear 90, 60 and 30 days before (renew or re-let);
 * the renter hears 60 days before, to talk about renewing.
 */
class LeaseEndingNotification extends Notification
{
    public function __construct(public Lease $lease, public int $daysLeft, public bool $forRenter = false) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'lease_ending',
            'lease_id' => $this->lease->id,
            'days_left' => $this->daysLeft,
            'end_date' => $this->lease->end_date?->toDateString(),
            'title' => $this->title(),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $end = $this->lease->end_date?->format('d/m/Y') ?? '—';
        $client = Client::find($this->lease->tenant_id);

        $mail = (new MailMessage)->subject($this->title())->greeting(__('Hello,'));

        if ($this->forRenter) {
            return $mail
                ->line(__('Your lease for :unit ends on :date.', ['unit' => $this->unitLabel(), 'date' => $end]))
                ->line(__('If you would like to stay, please talk to :client about renewing.', ['client' => $client?->name ?? __('your landlord')]))
                ->action(__('Open the portal'), $this->url());
        }

        return $mail
            ->line(__('The lease of :renter for :unit ends on :date.', [
                'renter' => $this->lease->renter !== null ? $this->lease->renter->display_name : __('a renter'),
                'unit' => $this->unitLabel(),
                'date' => $end,
            ]))
            ->line(__('Renew it, or plan the move-out and re-letting.'))
            ->action(__('Open the lease'), $this->url());
    }

    private function title(): string
    {
        return trans_choice('Lease ends in :count day: :unit|Lease ends in :count days: :unit', $this->daysLeft, ['unit' => $this->unitLabel()]);
    }

    private function unitLabel(): string
    {
        $unit = $this->lease->unit;

        return collect([$unit?->code, $unit?->property?->name])->filter()->implode(', ') ?: __('the unit');
    }

    private function url(): string
    {
        if ($this->forRenter) {
            $client = Client::find($this->lease->tenant_id);

            return url('/'.($client?->slug ?? '').'/portal');
        }

        return LeaseResource::getUrl('view', ['record' => $this->lease], panel: 'operator');
    }
}
