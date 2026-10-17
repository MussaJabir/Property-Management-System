<?php

declare(strict_types=1);

namespace App\Filament\Operator\Widgets;

use App\Filament\Operator\Pages\FrontDesk;
use App\Filament\Operator\Support\ShortStays;
use App\Models\Booking;
use App\Models\Unit;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

/**
 * Today, for workspaces with short stays: who arrives and who leaves today,
 * with a way into the front desk. Hidden everywhere else.
 */
class FrontDeskTodayWidget extends Widget
{
    protected string $view = 'filament.operator.widgets.front-desk-today';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    private const LIMIT = 5;

    public static function canView(): bool
    {
        return FrontDesk::canAccess() && Unit::query()->shortStay()->exists();
    }

    #[On('pms-front-desk-updated')]
    public function refreshDesk(): void {}

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $arrivals = Booking::query()
            ->where('status', Booking::STATUS_RESERVED)
            ->whereDate('check_in_date', '<=', today());

        $departures = Booking::query()
            ->where('status', Booking::STATUS_CHECKED_IN)
            ->whereDate('check_out_date', '<=', today());

        return [
            'sections' => [
                [
                    'label' => __('Arriving today'),
                    'count' => (clone $arrivals)->count(),
                    'rows' => (clone $arrivals)->with(['guest', 'unit'])->orderBy('check_in_date')->limit(self::LIMIT)->get(),
                    'empty' => __('No arrivals waiting.'),
                    'when' => fn (Booking $b): string => trans_choice(':count night|:count nights', $b->nights()),
                ],
                [
                    'label' => __('Leaving today'),
                    'count' => (clone $departures)->count(),
                    'rows' => (clone $departures)->with(['guest', 'unit'])->orderBy('check_out_date')->limit(self::LIMIT)->get(),
                    'empty' => __('Nobody is due to leave.'),
                    'when' => fn (Booking $b): string => __('by :time', ['time' => ShortStays::checkOutTime()]),
                ],
            ],
            'inHouse' => Booking::query()->where('status', Booking::STATUS_CHECKED_IN)->count(),
            'url' => FrontDesk::getUrl(),
        ];
    }
}
