<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use App\Filament\Operator\Actions\FrontDeskActions;
use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Filament\Operator\Support\ShortStays;
use App\Models\Booking;
use App\Models\Unit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Two weeks of bookings at a glance: one row per short-stay room, one
 * column per night. A free night opens New booking for that room and date.
 */
class FrontDeskCalendar extends Page
{
    public const DAYS = 14;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::FrontDesk;

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'front-desk/calendar';

    protected string $view = 'filament.operator.pages.front-desk-calendar';

    /** First night shown (Y-m-d); today when empty. */
    #[Url]
    public ?string $from = null;

    public static function getNavigationLabel(): string
    {
        return __('Calendar');
    }

    public function getTitle(): string
    {
        return __('Booking calendar');
    }

    public static function canAccess(): bool
    {
        return ShortStays::enabled() && BookingResource::canViewAny();
    }

    #[On('pms-front-desk-updated')]
    public function refreshCalendar(): void {}

    public function shift(int $days): void
    {
        $this->from = $this->start()->addDays($days)->toDateString();
    }

    public function goToday(): void
    {
        $this->from = null;
    }

    public function cellBookAction(): Action
    {
        return FrontDeskActions::book('cellBook');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [FrontDeskActions::book()];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $start = $this->start();
        $end = $start->copy()->addDays(self::DAYS);
        $days = collect(range(0, self::DAYS - 1))->map(fn (int $i): Carbon => $start->copy()->addDays($i));

        $units = Unit::query()->shortStay()->with('property')->get()
            ->sortBy(fn (Unit $unit): string => ($unit->property !== null ? $unit->property->name : '').'|'.$unit->code, SORT_NATURAL)
            ->values();

        $bookings = Booking::query()
            ->during($start, $end)
            ->whereIn('unit_id', $units->modelKeys())
            ->with('guest')
            ->get()
            ->groupBy('unit_id');

        $rows = $units->map(function (Unit $unit) use ($bookings, $days, $end): array {
            $cells = [];
            $i = 0;

            while ($i < self::DAYS) {
                $night = $days[$i];
                /** @var Booking|null $booking */
                $booking = $bookings->get($unit->id, collect())->first(
                    fn (Booking $b): bool => $b->check_in_date->lte($night) && $b->check_out_date->gt($night),
                );

                if ($booking === null) {
                    $cells[] = ['span' => 1, 'booking' => null, 'date' => $night->toDateString(), 'past' => $night->lt(today())];
                    $i++;

                    continue;
                }

                $until = $booking->check_out_date->lt($end) ? $booking->check_out_date : $end;
                $span = max(1, (int) $night->diffInDays($until));
                $cells[] = ['span' => $span, 'booking' => $booking, 'date' => $night->toDateString(), 'past' => false];
                $i += $span;
            }

            return [
                'code' => $unit->code,
                'property' => $unit->property !== null ? $unit->property->name : '',
                'id' => $unit->id,
                'cells' => $cells,
            ];
        });

        return [
            'days' => $days,
            'rows' => $rows,
            'canWork' => BookingResource::canCreate(),
            'range' => $start->format('d M').' – '.$end->copy()->subDay()->format('d M Y'),
        ];
    }

    private function start(): Carbon
    {
        try {
            return $this->from !== null ? Carbon::parse($this->from)->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }
}
