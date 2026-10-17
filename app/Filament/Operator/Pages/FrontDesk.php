<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use App\Filament\Operator\Actions\FrontDeskActions;
use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Filament\Operator\Resources\Units\UnitResource;
use App\Filament\Operator\Support\ShortStays;
use App\Models\Booking;
use App\Models\Lease;
use App\Models\Unit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

/**
 * Front desk: today's arrivals and departures, and every short-stay room
 * with its state and the next thing to do — check in, extend, check out —
 * one tap away. Only on plans with short stays.
 */
class FrontDesk extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::FrontDesk;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'front-desk';

    protected string $view = 'filament.operator.pages.front-desk';

    public static function getNavigationLabel(): string
    {
        return __('Front desk');
    }

    public function getTitle(): string
    {
        return __('Front desk');
    }

    public function getSubheading(): string
    {
        return now()->translatedFormat('l, j F Y').' · '.__('Check-in from :in · check-out by :out', [
            'in' => ShortStays::checkInTime(),
            'out' => ShortStays::checkOutTime(),
        ]);
    }

    public static function canAccess(): bool
    {
        return ShortStays::enabled() && BookingResource::canViewAny();
    }

    #[On('pms-front-desk-updated')]
    public function refreshBoard(): void
    {
        // Calling a Livewire method is enough to re-render.
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            FrontDeskActions::walkIn(),
            FrontDeskActions::book(),
            Action::make('calendar')
                ->label(__('Calendar'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(fn (): string => FrontDeskCalendar::getUrl()),
        ];
    }

    /* Row and room actions, rendered in the view with arguments. */

    public function roomCheckInAction(): Action
    {
        return FrontDeskActions::walkIn('roomCheckIn')->size('sm');
    }

    public function arrivalCheckInAction(): Action
    {
        return FrontDeskActions::checkIn('arrivalCheckIn')->size('sm');
    }

    public function stayCheckOutAction(): Action
    {
        return FrontDeskActions::checkOut('stayCheckOut')->size('sm');
    }

    public function stayExtendAction(): Action
    {
        return FrontDeskActions::extend('stayExtend')->size('sm')->link();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $today = today();

        $arrivals = Booking::query()
            ->where('status', Booking::STATUS_RESERVED)
            ->whereDate('check_in_date', '<=', $today)
            ->with(['guest', 'unit'])
            ->orderBy('check_in_date')
            ->get();

        $departures = Booking::query()
            ->where('status', Booking::STATUS_CHECKED_IN)
            ->whereDate('check_out_date', '<=', $today)
            ->with(['guest', 'unit', 'invoice'])
            ->orderBy('check_out_date')
            ->get();

        $rooms = $this->rooms();

        return [
            'arrivals' => $arrivals,
            'departures' => $departures,
            'rooms' => $rooms->groupBy(fn (array $room): string => $room['property']),
            'counts' => [
                'in_house' => $rooms->where('state', 'occupied')->count() + $rooms->where('state', 'leaving')->count(),
                'free' => $rooms->where('state', 'free')->count(),
                'total' => $rooms->count(),
            ],
            'canWork' => BookingResource::canCreate(),
            'pastCheckOut' => ShortStays::pastCheckOut(),
            'checkOutTime' => ShortStays::checkOutTime(),
            'unitsUrl' => UnitResource::canViewAny() ? UnitResource::getUrl('index') : null,
        ];
    }

    /**
     * Every short-stay room with its state today:
     * occupied | leaving (check-out today or overdue) | arriving | free | blocked.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rooms(): Collection
    {
        $today = today();

        $units = Unit::query()
            ->shortStay()
            ->with('property')
            ->withExists(['leases as leased' => fn ($q) => $q->where('status', Lease::STATUS_ACTIVE)])
            ->get()
            ->sortBy(fn (Unit $unit): string => ($unit->property !== null ? $unit->property->name : '').'|'.$unit->code, SORT_NATURAL);

        $bookings = Booking::query()
            ->live()
            ->whereIn('unit_id', $units->modelKeys())
            ->whereDate('check_out_date', '>=', $today)
            ->with('guest')
            ->orderBy('check_in_date')
            ->get()
            ->groupBy('unit_id');

        /** @var Collection<int, array<string, mixed>> $rooms */
        $rooms = $units->values()->map(function (Unit $unit) use ($bookings, $today): array {
            /** @var Collection<int, Booking> $mine */
            $mine = $bookings->get($unit->id, collect());
            $inHouse = $mine->first(fn (Booking $b): bool => $b->isCheckedIn());
            $arriving = $mine->first(fn (Booking $b): bool => $b->isReserved() && ! $b->check_in_date->isAfter($today));
            $next = $mine->first(fn (Booking $b): bool => $b->isReserved() && $b->check_in_date->isAfter($today));

            $state = match (true) {
                $inHouse !== null => $inHouse->check_out_date->lte($today) ? 'leaving' : 'occupied',
                $arriving !== null => 'arriving',
                (bool) $unit->getAttribute('leased') || $unit->status === Unit::STATUS_MAINTENANCE => 'blocked',
                default => 'free',
            };

            return [
                'id' => $unit->id,
                'code' => $unit->code,
                'property' => $unit->property !== null ? $unit->property->name : (string) __('Other'),
                'rate' => $unit->nightly_rate ? $unit->formatted_nightly_rate : null,
                'state' => $state,
                'booking' => $inHouse ?? $arriving,
                'next' => $next,
                'blocked_reason' => $unit->status === Unit::STATUS_MAINTENANCE ? __('Under maintenance') : __('On a lease'),
            ];
        });

        return $rooms;
    }
}
