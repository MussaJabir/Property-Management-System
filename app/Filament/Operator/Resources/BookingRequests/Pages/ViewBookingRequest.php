<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\BookingRequests\Pages;

use App\Filament\Operator\Resources\BookingRequests\BookingRequestResource;
use App\Filament\Operator\Resources\Units\UnitResource;
use App\Models\BookingRequest;
use App\Models\BookingRequestNote;
use App\Support\RequestAlerts;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * One request from the website, with everything needed to answer it: who
 * and how to reach them, what they want, their whole message, the room,
 * and a history the team adds notes to. Calls and WhatsApp taps are noted
 * there too.
 *
 * @property BookingRequest $record
 */
class ViewBookingRequest extends ViewRecord
{
    protected static string $resource = BookingRequestResource::class;

    protected string $view = 'filament.operator.resources.booking-requests.view';

    public string $note = '';

    public function getTitle(): string
    {
        return $this->record->full_name;
    }

    public function getSubheading(): string
    {
        return __('Asking about :room · received :when', [
            'room' => $this->record->unit?->publicTitle() ?? '',
            'when' => $this->record->created_at?->format('d/m/Y H:i').' ('.$this->record->created_at?->diffForHumans().')',
        ]);
    }

    public function phoneCallAction(): Action
    {
        return BookingRequestResource::callAction();
    }

    public function whatsappAction(): Action
    {
        return BookingRequestResource::whatsAppAction(log: true);
    }

    public function confirmAction(): Action
    {
        return BookingRequestResource::confirmAction();
    }

    public function handledAction(): Action
    {
        return BookingRequestResource::handledAction();
    }

    public function declineAction(): Action
    {
        return BookingRequestResource::declineAction();
    }

    public function saveNote(): void
    {
        $this->validate(['note' => ['required', 'string', 'max:1000']]);

        $this->record->log(BookingRequestNote::TYPE_NOTE, trim($this->note), auth()->id());
        $this->note = '';

        Notification::make()->success()->title(__('Note saved'))->send();
    }

    /** Called when Call or WhatsApp is tapped (the link itself opens the phone or WhatsApp). */
    public function logContact(string $type): void
    {
        if (! in_array($type, [BookingRequestNote::TYPE_CALL, BookingRequestNote::TYPE_WHATSAPP], true)) {
            return;
        }

        $this->record->log($type, null, auth()->id());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $request = $this->record->loadMissing('unit.property.location');
        $unit = $request->unit;

        return [
            'request' => $request,
            'phone' => RequestAlerts::display($request->phone),
            'wants' => BookingRequestResource::wants($request),
            'statusLabel' => BookingRequestResource::statusLabels()[$request->status] ?? $request->status,
            'askedBefore' => $request->fromSamePhone()->count(),
            'history' => $request->notes()->with('user')->get(),
            'unit' => $unit,
            'photo' => $unit?->coverImageUrl('card'),
            'prices' => $unit?->publicPrices() ?? [],
            'otherOpen' => $unit !== null ? BookingRequest::query()->open()->where('unit_id', $unit->id)->whereKeyNot($request->getKey())->count() : 0,
            'unitUrl' => $unit !== null && UnitResource::canEdit($unit) ? UnitResource::getUrl('edit', ['record' => $unit]) : null,
            'publicUrl' => $unit !== null && $unit->isListedPublicly() ? route('public.units.show', ['tenant' => $request->tenant_id, 'unit' => $unit->id]) : null,
        ];
    }
}
