<?php

declare(strict_types=1);

namespace App\Filament\Operator\Widgets;

use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Filament\Operator\Resources\Units\UnitResource;
use App\Filament\Operator\Widgets\Concerns\RefreshesAfterPayment;
use App\Models\Lease;
use App\Models\Unit;
use Filament\Widgets\Widget;

/**
 * "Coming up": leases ending in the next 30 days (renew or re-let) and
 * units standing empty. Tenant-scoped via the models.
 */
class ComingUpWidget extends Widget
{
    use RefreshesAfterPayment;

    protected string $view = 'filament.operator.widgets.coming-up';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    private const LIMIT = 4;

    public static function canView(): bool
    {
        return LeaseResource::canViewAny() || UnitResource::canViewAny();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $endingQuery = Lease::query()->active()->expiringWithin(30);
        $ending = (clone $endingQuery)
            ->with(['renter', 'unit.property'])
            ->orderBy('end_date')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Lease $lease): array => [
                'title' => $lease->renter !== null ? $lease->renter->display_name : __('Unknown renter'),
                'sub' => collect([$lease->unit?->code, $lease->unit?->property?->name])->filter()->implode(' · '),
                'when' => $lease->end_date?->format('d/m/Y'),
                'url' => LeaseResource::canEdit($lease) ? LeaseResource::getUrl('edit', ['record' => $lease]) : null,
            ]);

        $vacantQuery = Unit::query()->vacant();
        $vacant = (clone $vacantQuery)
            ->with('property')
            ->orderBy('code')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Unit $unit): array => [
                'title' => $unit->code,
                'sub' => (string) $unit->property?->name,
                'when' => $unit->rent_amount > 0 ? $unit->formatted_rent : null,
                'url' => UnitResource::canEdit($unit) ? UnitResource::getUrl('edit', ['record' => $unit]) : null,
            ]);

        $sections = [
            'ending' => [
                'label' => __('Leases ending in 30 days'),
                'count' => $endingQuery->count(),
                'rows' => $ending,
                'empty' => __('No leases end in the next 30 days.'),
            ],
            'vacant' => [
                'label' => __('Vacant units'),
                'count' => $vacantQuery->count(),
                'rows' => $vacant,
                'empty' => __('Every unit is taken.'),
            ],
        ];

        // Only the lists this role may open.
        if (! LeaseResource::canViewAny()) {
            unset($sections['ending']);
        }
        if (! UnitResource::canViewAny()) {
            unset($sections['vacant']);
        }

        return ['sections' => $sections];
    }
}
