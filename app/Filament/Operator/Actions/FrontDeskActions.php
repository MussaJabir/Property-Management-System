<?php

declare(strict_types=1);

namespace App\Filament\Operator\Actions;

use App\Exceptions\RoomNotAvailable;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use App\Services\FrontDesk\ShortStayService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Component;
use RuntimeException;

/**
 * The front desk's everyday actions, shared by the Front desk page and the
 * Bookings list:
 *
 *  - walkIn:    one screen — room, name, phone, nights, optional payment —
 *               and the guest is checked in with the bill issued
 *  - book:      a reservation for later dates
 *  - checkIn / checkOut / extend / cancel / noShow on an existing booking
 *
 * Room-scoped actions take a `unit` argument, booking-scoped ones a
 * `booking` argument or the table row. All of them dispatch
 * `pms-front-desk-updated` so the page refreshes.
 */
final class FrontDeskActions
{
    public static function walkIn(string $name = 'walkIn'): Action
    {
        return Action::make($name)
            ->label(__('Check in'))
            ->icon(Heroicon::OutlinedKey)
            ->color('primary')
            ->authorize(fn (): bool => BookingResource::canCreate())
            ->modalHeading(fn (array $arguments): string => ($unit = self::unit($arguments)) !== null
                ? __('Check in to :unit', ['unit' => $unit->code])
                : __('Check in a guest'))
            ->modalDescription(__('Name and phone are enough. ID details can be added later on the guest\'s page.'))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Check in'))
            ->fillForm(fn (array $arguments): array => [
                'unit_id' => self::unit($arguments)?->id,
                'nights' => 1,
                'nightly_rate' => self::rateOf(self::unit($arguments)),
                'guests_count' => 1,
                'channel' => Payment::METHOD_CASH,
            ])
            ->schema(fn (array $arguments): array => [
                self::roomSelect(fn (): array => self::freeRooms(today(), today()->addDay()))
                    ->hidden(self::unit($arguments) !== null),

                ...self::guestFields(),

                Grid::make(3)->schema([
                    TextInput::make('nights')
                        ->label(__('Nights'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(365)
                        ->live(debounce: 400),
                    self::rateInput(),
                    TextInput::make('guests_count')
                        ->label(__('Guests'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50),
                ]),

                Text::make(fn (Get $get): string => self::stayLine(today(), (int) $get('nights'), $get('nightly_rate'))),

                self::paymentFieldset(__('Paid now (optional)')),
            ])
            ->action(function (array $data, array $arguments, Action $action, Component $livewire, ShortStayService $stays): void {
                $unit = self::unit($arguments) ?? Unit::query()->shortStay()->find($data['unit_id'] ?? null);
                $nights = max(1, (int) $data['nights']);

                $booking = self::attempt($action, fn (): Booking => $stays->book(
                    $unit ?? throw new RuntimeException(__('Pick a room.')),
                    ['full_name' => $data['full_name'], 'phone' => $data['phone']],
                    today(),
                    today()->addDays($nights),
                    self::cents($data['nightly_rate'] ?? null),
                    (int) ($data['guests_count'] ?: 1),
                    checkInNow: true,
                ));

                $payment = self::takePayment($booking->invoice, $data);

                self::notify(
                    __(':guest checked in to :unit', ['guest' => $booking->guest?->full_name, 'unit' => $booking->unit?->code]),
                    $booking,
                    $payment,
                );
                self::refresh($livewire);
            });
    }

    public static function book(string $name = 'book'): Action
    {
        return Action::make($name)
            ->label(__('New booking'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->authorize(fn (): bool => BookingResource::canCreate())
            ->modalHeading(__('New booking'))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Save booking'))
            ->fillForm(fn (array $arguments): array => [
                'unit_id' => self::unit($arguments)?->id,
                // From the calendar: the clicked room and night.
                'check_in_date' => self::date($arguments)->toDateString(),
                'check_out_date' => self::date($arguments)->addDay()->toDateString(),
                'nightly_rate' => self::rateOf(self::unit($arguments)),
                'guests_count' => 1,
            ])
            ->schema([
                Grid::make(2)->schema([
                    DatePicker::make('check_in_date')
                        ->label(__('Arrives'))
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(today())
                        ->live()
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                            if ($state !== null && ($get('check_out_date') === null || Carbon::parse($get('check_out_date'))->lte(Carbon::parse($state)))) {
                                $set('check_out_date', Carbon::parse($state)->addDay()->toDateString());
                            }
                        }),
                    DatePicker::make('check_out_date')
                        ->label(__('Leaves'))
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->after('check_in_date')
                        ->live(),
                ]),

                self::roomSelect(fn (Get $get): array => filled($get('check_in_date')) && filled($get('check_out_date'))
                    ? self::freeRooms(Carbon::parse($get('check_in_date')), Carbon::parse($get('check_out_date')))
                    : []),

                ...self::guestFields(),

                Grid::make(2)->schema([
                    self::rateInput(),
                    TextInput::make('guests_count')
                        ->label(__('Guests'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50),
                ]),

                Text::make(function (Get $get): string {
                    if (blank($get('check_in_date')) || blank($get('check_out_date'))) {
                        return '';
                    }

                    $in = Carbon::parse($get('check_in_date'));

                    return self::stayLine($in, (int) $in->diffInDays(Carbon::parse($get('check_out_date'))), $get('nightly_rate'));
                }),

                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->rows(2)
                    ->placeholder(__('Optional, e.g. arriving late, airport pickup')),
            ])
            ->action(function (array $data, Action $action, Component $livewire, ShortStayService $stays): void {
                $unit = Unit::query()->shortStay()->find($data['unit_id'] ?? null);

                $booking = self::attempt($action, fn (): Booking => $stays->book(
                    $unit ?? throw new RuntimeException(__('Pick a room.')),
                    ['full_name' => $data['full_name'], 'phone' => $data['phone']],
                    Carbon::parse($data['check_in_date']),
                    Carbon::parse($data['check_out_date']),
                    self::cents($data['nightly_rate'] ?? null),
                    (int) ($data['guests_count'] ?: 1),
                    notes: $data['notes'] ?? null,
                ));

                self::notify(__('Booked :unit for :guest', ['unit' => $booking->unit?->code, 'guest' => $booking->guest?->full_name]), $booking);
                self::refresh($livewire);
            });
    }

    public static function checkIn(string $name = 'checkIn'): Action
    {
        return Action::make($name)
            ->label(__('Check in'))
            ->icon(Heroicon::OutlinedKey)
            ->color('primary')
            ->authorize(fn (): bool => BookingResource::canCreate())
            ->visible(fn (?Model $record, array $arguments): bool => self::booking($record, $arguments)?->canCheckIn() ?? false)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalHeading(fn (?Model $record, array $arguments): string => __('Check in :guest?', ['guest' => self::booking($record, $arguments)?->guest?->full_name]))
            ->modalDescription(fn (?Model $record, array $arguments): string => self::describe(self::booking($record, $arguments)))
            ->modalSubmitActionLabel(__('Check in'))
            ->action(function (?Model $record, array $arguments, Action $action, Component $livewire): void {
                $booking = self::booking($record, $arguments);
                self::attempt($action, fn () => $booking?->checkIn());

                Notification::make()->success()->title(__(':guest checked in', ['guest' => $booking?->guest?->full_name]))->send();
                self::refresh($livewire);
            });
    }

    public static function checkOut(string $name = 'checkOut'): Action
    {
        return Action::make($name)
            ->label(__('Check out'))
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('gray')
            ->authorize(fn (): bool => BookingResource::canCreate())
            ->visible(fn (?Model $record, array $arguments): bool => self::booking($record, $arguments)?->isCheckedIn() ?? false)
            ->modalHeading(fn (?Model $record, array $arguments): string => __('Check out :guest?', ['guest' => self::booking($record, $arguments)?->guest?->full_name]))
            ->modalDescription(function (?Model $record, array $arguments): string {
                $booking = self::booking($record, $arguments);
                $invoice = $booking?->invoice;

                return $invoice !== null && $invoice->balanceDue() > 0
                    ? __(':describe. Still owed: :balance.', ['describe' => self::describe($booking), 'balance' => $invoice->formatted_balance])
                    : __(':describe. Fully paid.', ['describe' => self::describe($booking)]);
            })
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Check out'))
            ->fillForm(fn (?Model $record, array $arguments): array => [
                'amount' => ($invoice = self::booking($record, $arguments)?->invoice) !== null && $invoice->balanceDue() > 0
                    ? (int) round($invoice->balanceDue() / 100)
                    : null,
                'channel' => Payment::METHOD_CASH,
            ])
            ->schema(fn (?Model $record, array $arguments): array => (self::booking($record, $arguments)?->invoice?->balanceDue() ?? 0) > 0
                ? [self::paymentFieldset(__('Take the balance now (optional)'))]
                : [])
            ->action(function (array $data, ?Model $record, array $arguments, Action $action, Component $livewire): void {
                $booking = self::booking($record, $arguments);
                $payment = self::takePayment($booking?->invoice, $data);
                self::attempt($action, fn () => $booking?->checkOut());

                $invoice = $booking?->invoice?->fresh();
                $balance = $invoice !== null ? $invoice->balanceDue() : 0;
                $notification = Notification::make()
                    ->title(__(':guest checked out', ['guest' => $booking?->guest?->full_name]))
                    ->body($invoice !== null && $balance > 0 ? __('Still owed: :balance', ['balance' => $invoice->formatted_balance]) : null);
                ($balance > 0 ? $notification->warning() : $notification->success())
                    ->actions(self::receiptActions($payment))
                    ->send();
                self::refresh($livewire);
            });
    }

    public static function extend(string $name = 'extend'): Action
    {
        return Action::make($name)
            ->label(__('Extend'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('gray')
            ->authorize(fn (): bool => BookingResource::canCreate())
            ->visible(fn (?Model $record, array $arguments): bool => self::booking($record, $arguments)?->isLive() ?? false)
            ->modalHeading(fn (?Model $record, array $arguments): string => __('Extend :guest\'s stay', ['guest' => self::booking($record, $arguments)?->guest?->full_name]))
            ->modalDescription(fn (?Model $record, array $arguments): string => __('Leaves :date now.', ['date' => self::booking($record, $arguments)?->check_out_date->format('D d/m/Y')]))
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('Extend'))
            ->fillForm(['extra_nights' => 1])
            ->schema(fn (?Model $record, array $arguments): array => [
                TextInput::make('extra_nights')
                    ->label(__('Extra nights'))
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(365)
                    ->live(debounce: 400),
                Text::make(function (Get $get) use ($record, $arguments): string {
                    $booking = self::booking($record, $arguments);
                    $extra = max(0, (int) $get('extra_nights'));

                    return $booking === null ? '' : __('New check-out :date · adds :amount', [
                        'date' => $booking->check_out_date->copy()->addDays($extra)->format('D d/m/Y'),
                        'amount' => self::money($booking->currency, $booking->nightly_rate * $extra),
                    ]);
                }),
            ])
            ->action(function (array $data, ?Model $record, array $arguments, Action $action, Component $livewire, ShortStayService $stays): void {
                $booking = self::booking($record, $arguments);
                if ($booking === null) {
                    return;
                }

                $booking = self::attempt($action, fn (): Booking => $stays->extend($booking, $booking->check_out_date->copy()->addDays(max(1, (int) $data['extra_nights']))));

                Notification::make()->success()
                    ->title(__('Stay extended to :date', ['date' => $booking->check_out_date->format('d/m/Y')]))
                    ->body(__('Bill now :total', ['total' => $booking->invoice?->formatted_total]))
                    ->send();
                self::refresh($livewire);
            });
    }

    public static function cancel(string $name = 'cancelBooking'): Action
    {
        return Action::make($name)
            ->label(__('Cancel booking'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize(fn (): bool => BookingResource::canCreate())
            ->visible(fn (?Model $record, array $arguments): bool => self::booking($record, $arguments)?->isReserved() ?? false)
            ->requiresConfirmation()
            ->modalDescription(__('The nights become free again. An unpaid bill is cancelled; money already paid stays on it.'))
            ->action(function (?Model $record, array $arguments, Action $action, Component $livewire): void {
                self::attempt($action, fn () => self::booking($record, $arguments)?->cancel());
                Notification::make()->success()->title(__('Booking cancelled'))->send();
                self::refresh($livewire);
            });
    }

    public static function noShow(string $name = 'noShow'): Action
    {
        return Action::make($name)
            ->label(__('No-show'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('gray')
            ->authorize(fn (): bool => BookingResource::canCreate())
            ->visible(fn (?Model $record, array $arguments): bool => ($booking = self::booking($record, $arguments)) !== null
                && $booking->isReserved() && ! $booking->check_in_date->isFuture())
            ->requiresConfirmation()
            ->modalDescription(__('The guest never arrived. The room is freed; the bill stays.'))
            ->action(function (?Model $record, array $arguments, Action $action, Component $livewire): void {
                self::attempt($action, fn () => self::booking($record, $arguments)?->markNoShow());
                Notification::make()->success()->title(__('Marked as no-show'))->send();
                self::refresh($livewire);
            });
    }

    /**
     * Short-stay rooms free for every night in [in, out): not let on a
     * lease, not under maintenance, no live booking overlapping.
     *
     * @return array<string, string>
     */
    public static function freeRooms(Carbon $in, Carbon $out): array
    {
        return Unit::query()
            ->shortStay()
            ->where('status', '!=', Unit::STATUS_MAINTENANCE)
            ->whereDoesntHave('leases', fn ($q) => $q->where('status', Lease::STATUS_ACTIVE))
            ->whereDoesntHave('bookings', fn ($q) => (new Booking)->scopeDuring($q, $in, $out))
            ->with('property')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Unit $unit): array => [$unit->id => collect([
                $unit->code,
                $unit->property?->name,
                $unit->nightly_rate ? __(':rate a night', ['rate' => $unit->formatted_nightly_rate]) : null,
            ])->filter()->implode(' · ')])
            ->all();
    }

    /* ----- Pieces ----- */

    private static function roomSelect(\Closure $options): Select
    {
        return Select::make('unit_id')
            ->label(__('Room'))
            ->required()
            ->searchable()
            ->native(false)
            ->live()
            ->options($options)
            ->noSearchResultsMessage(__('No room is free for those nights.'))
            ->afterStateUpdated(fn (?string $state, Set $set) => $set('nightly_rate', self::rateOf($state !== null ? Unit::find($state) : null)));
    }

    /** @return array<int, Grid> */
    private static function guestFields(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('full_name')
                    ->label(__('Guest name'))
                    ->required()
                    ->maxLength(150)
                    ->autofocus(),
                TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel()
                    ->required()
                    ->placeholder('0712345678')
                    ->maxLength(20)
                    ->helperText(__('A returning guest is found by phone.')),
            ]),
        ];
    }

    private static function rateInput(): TextInput
    {
        return TextInput::make('nightly_rate')
            ->label(__('Rate a night'))
            ->numeric()
            ->required()
            ->minValue(0)
            ->live(debounce: 400);
    }

    private static function paymentFieldset(string $label): Fieldset
    {
        return Fieldset::make($label)
            ->columns(1)
            ->schema([
                TextInput::make('amount')
                    ->label(__('Amount received'))
                    ->numeric()
                    ->minValue(1)
                    ->placeholder(__('Leave empty if not paying now'))
                    ->live(debounce: 400),
                ToggleButtons::make('channel')
                    ->label(__('Paid by'))
                    ->options(RecordPaymentAction::channels())
                    ->inline()
                    ->visible(fn (Get $get): bool => filled($get('amount'))),
                TextInput::make('reference')
                    ->label(__('Reference / transaction ID'))
                    ->placeholder(__('Optional'))
                    ->maxLength(60)
                    ->visible(fn (Get $get): bool => filled($get('amount')) && $get('channel') !== Payment::METHOD_CASH),
            ]);
    }

    /** @param  array<string, mixed>  $data */
    private static function takePayment(?Invoice $invoice, array $data): ?Payment
    {
        $amount = self::cents($data['amount'] ?? null);

        if ($invoice === null || $amount === null || $amount <= 0) {
            return null;
        }

        $payment = RecordPaymentAction::record($invoice, $amount, (string) ($data['channel'] ?? Payment::METHOD_CASH), $data['reference'] ?? null);
        $invoice->refresh();

        return $payment;
    }

    private static function notify(string $title, Booking $booking, ?Payment $payment = null): void
    {
        $invoice = $booking->invoice;

        Notification::make()
            ->success()
            ->title($title)
            ->body(collect([
                self::describe($booking),
                $invoice !== null ? __('Bill :number · :total', ['number' => $invoice->invoice_number, 'total' => $invoice->formatted_total]) : null,
                $invoice !== null && $invoice->balanceDue() > 0 ? __('owed :balance', ['balance' => $invoice->formatted_balance]) : ($invoice !== null ? __('paid in full') : null),
            ])->filter()->implode(' · '))
            ->actions(self::receiptActions($payment))
            ->duration(12000)
            ->send();
    }

    /** @return array<int, Action> */
    private static function receiptActions(?Payment $payment): array
    {
        $receipt = $payment?->receipt;

        return $receipt === null ? [] : [
            Action::make('downloadReceipt')
                ->label(__('Download receipt'))
                ->button()
                ->url(route('filament.operator.receipts.pdf', ['receipt' => $receipt->getKey()]), shouldOpenInNewTab: true),
        ];
    }

    /**
     * Run a front desk step; a refusal (room taken, wrong state, bad dates)
     * becomes a red toast and the modal stays open.
     *
     * @template T
     *
     * @param  callable(): T  $step
     * @return T
     */
    private static function attempt(Action $action, callable $step): mixed
    {
        try {
            return $step();
        } catch (RoomNotAvailable|InvalidArgumentException|RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();

            throw $e;
        }
    }

    private static function refresh(Component $livewire): void
    {
        $livewire->dispatch('pms-front-desk-updated');
        $livewire->dispatch('pms-payment-recorded');
    }

    private static function booking(?Model $record, array $arguments): ?Booking
    {
        if ($record instanceof Booking) {
            return $record;
        }

        $id = $arguments['booking'] ?? null;

        return is_string($id) ? Booking::query()->with(['guest', 'unit', 'invoice'])->find($id) : null;
    }

    private static function unit(array $arguments): ?Unit
    {
        $id = $arguments['unit'] ?? null;

        return is_string($id) ? Unit::query()->shortStay()->find($id) : null;
    }

    private static function date(array $arguments): Carbon
    {
        $date = $arguments['date'] ?? null;

        try {
            $date = is_string($date) ? Carbon::parse($date)->startOfDay() : today();
        } catch (\Throwable) {
            $date = today();
        }

        return $date->lt(today()) ? today() : $date;
    }

    private static function rateOf(?Unit $unit): ?int
    {
        return $unit?->nightly_rate !== null ? (int) round($unit->nightly_rate / 100) : null;
    }

    private static function cents(mixed $wholeAmount): ?int
    {
        return is_numeric($wholeAmount) ? (int) round(((float) $wholeAmount) * 100) : null;
    }

    private static function money(string $currency, int $cents): string
    {
        return $currency.' '.number_format($cents / 100, 0, '.', ',');
    }

    /** "Check-out Mon 20/10/2026 · 3 nights · TZS 240,000" */
    private static function stayLine(Carbon $in, int $nights, mixed $rate): string
    {
        if ($nights < 1) {
            return '';
        }

        return collect([
            __('Check-out :date', ['date' => $in->copy()->addDays($nights)->format('D d/m/Y')]),
            trans_choice(':count night|:count nights', $nights),
            is_numeric($rate) ? self::money('TZS', (int) round((float) $rate * 100) * $nights) : null,
        ])->filter()->implode(' · ');
    }

    /** "R1 · 17/10 → 20/10 · 3 nights" */
    private static function describe(?Booking $booking): string
    {
        if ($booking === null) {
            return '';
        }

        return collect([
            $booking->unit?->code,
            $booking->check_in_date->format('d/m').' → '.$booking->check_out_date->format('d/m'),
            trans_choice(':count night|:count nights', $booking->nights()),
        ])->filter()->implode(' · ');
    }
}
