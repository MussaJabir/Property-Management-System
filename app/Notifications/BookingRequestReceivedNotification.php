<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BookingRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the workspace's staff that someone asked for a room on the public
 * website: in the dashboard bell and by email.
 */
class BookingRequestReceivedNotification extends Notification
{
    public function __construct(public BookingRequest $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->summary())
            ->icon('heroicon-o-calendar-days')
            ->actions([
                Action::make('open')->label(__('Open requests'))->url(url('/manage/booking-requests'))->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? '']))
            ->line($this->summary())
            ->line(__('Phone: :phone', ['phone' => $this->request->phone]))
            ->action(__('Open requests'), url('/manage/booking-requests'));
    }

    private function title(): string
    {
        return $this->request->isStay()
            ? __('New booking request: :room', ['room' => $this->request->unit?->code ?? ''])
            : __('New enquiry: :room', ['room' => $this->request->unit?->code ?? '']);
    }

    private function summary(): string
    {
        $r = $this->request;

        return $r->isStay()
            ? __(':name wants :nights night(s) from :from, :guests guest(s).', [
                'name' => $r->full_name,
                'nights' => $r->nights(),
                'from' => $r->check_in_date?->format('d/m/Y'),
                'guests' => $r->guests_count,
            ])
            : __(':name asked about the room, moving in around :date.', [
                'name' => $r->full_name,
                'date' => $r->move_in_date?->format('d/m/Y') ?? __('soon'),
            ]);
    }
}
