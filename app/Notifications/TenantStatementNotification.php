<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Client;
use App\Models\Renter;
use App\Services\Payments\TenantStatement;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** A renter's statement for a period, emailed with the PDF attached. */
class TenantStatementNotification extends Notification
{
    public function __construct(public Renter $renter, public Carbon $from, public Carbon $to) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = Client::find($this->renter->tenant_id);
        $statement = app(TenantStatement::class);
        $summary = $statement->build($this->renter, $this->from, $this->to);
        $closing = $summary['closing'];
        $money = $summary['currency'].' '.number_format(abs($closing) / 100, 0, '.', ',');

        return (new MailMessage)
            ->subject(__('Your statement from :client', ['client' => $client?->name ?? 'PMS']))
            ->greeting(__('Hello :name,', ['name' => $this->renter->display_name]))
            ->line(__('Your statement for :from – :to is attached.', ['from' => $this->from->format('d/m/Y'), 'to' => $this->to->format('d/m/Y')]))
            ->line(match (true) {
                $closing > 0 => __('Balance owed: :amount', ['amount' => $money]),
                $closing < 0 => __('You have credit of :amount, used on your next invoice.', ['amount' => $money]),
                default => __('Your balance is clear. Thank you.'),
            })
            ->attachData($statement->pdf($this->renter, $this->from, $this->to), 'statement.pdf', ['mime' => 'application/pdf']);
    }
}
