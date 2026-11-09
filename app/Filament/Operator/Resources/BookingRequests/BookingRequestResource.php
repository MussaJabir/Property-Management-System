<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\BookingRequests;

use App\Exceptions\RoomNotAvailable;
use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\BookingRequests\Pages\ListBookingRequests;
use App\Filament\Operator\Resources\BookingRequests\Pages\ViewBookingRequest;
use App\Filament\Operator\Support\ShortStays;
use App\Models\BookingRequest;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;

/**
 * Requests sent from room pages on the public website. Stays are confirmed
 * (which books the room and issues the bill) or declined; enquiries for
 * leased rooms are marked as called back. Shown on every plan: enquiries
 * come from long-term rooms too.
 */
class BookingRequestResource extends Resource
{
    protected static ?string $model = BookingRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 5;

    /** Under Front desk on plans with short stays, else with leases. */
    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return ShortStays::enabled() ? NavGroup::FrontDesk : NavGroup::RentersLeases;
    }

    public static function getNavigationLabel(): string
    {
        return __('Requests');
    }

    public static function getModelLabel(): string
    {
        return __('Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Requests');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BookingRequest::query()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('unit.property'))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No requests yet'))
            ->emptyStateDescription(__('Requests from your website\'s room pages appear here.'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Received'))
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i'),
                TextColumn::make('unit.code')
                    ->label(__('Room'))
                    ->description(fn (BookingRequest $r): ?string => $r->unit?->property?->name),
                TextColumn::make('full_name')
                    ->label(__('Name'))
                    ->description(fn (BookingRequest $r): string => $r->phone)
                    ->searchable(['full_name', 'phone']),
                TextColumn::make('kind')
                    ->label(__('Wants'))
                    ->formatStateUsing(fn (BookingRequest $r): string => self::wants($r))
                    ->description(fn (BookingRequest $r): ?string => $r->message)
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        BookingRequest::STATUS_NEW => 'warning',
                        BookingRequest::STATUS_CONFIRMED, BookingRequest::STATUS_HANDLED => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(self::statusLabels())
                    ->default(BookingRequest::STATUS_NEW),
            ])
            ->recordActions([
                self::confirmAction(),
                self::handledAction(),
                self::declineAction(),
                self::whatsAppAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingRequests::route('/'),
            'view' => ViewBookingRequest::route('/{record}'),
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            BookingRequest::STATUS_NEW => __('New'),
            BookingRequest::STATUS_CONFIRMED => __('Booked'),
            BookingRequest::STATUS_HANDLED => __('Called back'),
            BookingRequest::STATUS_DECLINED => __('Declined'),
        ];
    }

    /** Reload the page so the menu's "new requests" count goes down too. */
    private static function refreshMenuBadge(): \Closure
    {
        return fn (Component $livewire, BookingRequest $record) => $livewire->redirect(
            $livewire instanceof ViewBookingRequest ? self::getUrl('view', ['record' => $record]) : self::getUrl('index'),
            navigate: true,
        );
    }

    public static function handledAction(): Action
    {
        return Action::make('handled')
            ->label(__('Called back'))
            ->icon(Heroicon::OutlinedCheck)
            ->visible(fn (BookingRequest $record): bool => $record->isOpen() && ! $record->isStay())
            ->action(function (BookingRequest $record): void {
                $record->markHandled(auth()->id());
                Notification::make()->success()->title(__('Marked as called back'))->send();
            })
            ->after(self::refreshMenuBadge());
    }

    public static function declineAction(): Action
    {
        return Action::make('decline')
            ->label(__('Decline'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (BookingRequest $record): bool => $record->isOpen())
            ->modalHeading(__('Decline this request?'))
            ->schema([
                TextInput::make('reason')->label(__('Reason (for your records)'))->placeholder(__('e.g. Room not free those dates'))->maxLength(200),
            ])
            ->action(function (BookingRequest $record, array $data): void {
                $record->decline($data['reason'] ?? null, auth()->id());
                Notification::make()->success()->title(__('Request declined'))->send();
            })
            ->after(self::refreshMenuBadge());
    }

    /**
     * Opens WhatsApp with a greeting. On the request page ($log) the tap is
     * also noted in its history.
     */
    public static function whatsAppAction(bool $log = false): Action
    {
        return Action::make('whatsapp')
            ->label(__('WhatsApp'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->url(fn (BookingRequest $record): string => 'https://wa.me/'.ltrim($record->phone, '+').'?text='.rawurlencode(__('Hello :name, this is :business about your request for :room.', [
                'name' => $record->full_name,
                'business' => Client::find($record->tenant_id)->name ?? '',
                'room' => $record->unit !== null ? $record->unit->code : '',
            ])))
            ->openUrlInNewTab()
            ->extraAttributes($log ? ['wire:click' => "logContact('whatsapp')"] : []);
    }

    /** Dials the number (phones) and notes the call in the request's history. */
    public static function callAction(): Action
    {
        return Action::make('phoneCall')
            ->label(__('Call'))
            ->icon(Heroicon::OutlinedPhone)
            ->url(fn (BookingRequest $record): string => 'tel:'.$record->phone)
            ->extraAttributes(['wire:click' => "logContact('call')"]);
    }

    public static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label(__('Confirm & book'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->visible(fn (BookingRequest $record): bool => $record->isOpen() && $record->isStay() && ShortStays::enabled())
            ->requiresConfirmation()
            ->modalHeading(fn (BookingRequest $record): string => __('Book :room for :name?', ['room' => $record->unit !== null ? $record->unit->code : '', 'name' => $record->full_name]))
            ->modalDescription(fn (BookingRequest $record): string => self::wants($record).'. '.__('The bill is issued, due on arrival. Call the guest to tell them.'))
            ->modalSubmitActionLabel(__('Book the room'))
            ->action(function (BookingRequest $r): void {
                try {
                    $booking = $r->confirm(auth()->id());
                } catch (RoomNotAvailable|InvalidArgumentException|RuntimeException $e) {
                    Notification::make()->danger()->title(__('Could not book'))->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()
                    ->title(__('Booked'))
                    ->body(__(':name is booked into :room from :date.', [
                        'name' => $r->full_name,
                        'room' => $r->unit !== null ? $r->unit->code : '',
                        'date' => $booking->check_in_date->format('d/m/Y'),
                    ]))
                    ->send();
            })
            ->after(self::refreshMenuBadge());
    }

    public static function wants(BookingRequest $r): string
    {
        return $r->isStay()
            ? __(':nights from :from · :guests', [
                'nights' => trans_choice(':count night|:count nights', $r->nights()),
                'from' => $r->check_in_date?->format('d/m/Y'),
                'guests' => trans_choice(':count guest|:count guests', (int) $r->guests_count),
            ])
            : ($r->move_in_date !== null
                ? __('Monthly · move in around :date', ['date' => $r->move_in_date->format('d/m/Y')])
                : __('Monthly · asking about the room'));
    }
}
