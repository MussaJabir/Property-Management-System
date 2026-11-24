<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Leases\Pages;

use App\Filament\Operator\Resources\Leases\LeaseActions;
use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Models\Lease;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One lease: its terms, a warning when it ends soon, renewal links and the
 * history. Renew, end and terminate are in the header.
 */
class ViewLease extends ViewRecord
{
    protected static string $resource = LeaseResource::class;

    protected string $view = 'filament.operator.resources.leases.view';

    /** Show the "ends soon" warning this many days before the end date. */
    private const WARN_DAYS = 90;

    public function getTitle(): string|Htmlable
    {
        $renter = $this->lease()->renter;

        return __('Lease · :name', ['name' => $renter !== null ? $renter->display_name : __('Unknown renter')]);
    }

    public function getSubheading(): ?string
    {
        $unit = $this->lease()->unit;

        return collect([$unit?->code, $unit?->property?->name])->filter()->implode(', ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            LeaseActions::renew(),
            LeaseActions::activate(),
            ActionGroup::make([
                EditAction::make(),
                LeaseActions::end(),
                LeaseActions::terminate(),
            ])->label(__('More'))->button()->color('gray'),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $lease = $this->lease()->load(['renter', 'unit.property', 'renewal', 'renewedFrom']);
        $money = fn (?int $minor): string => $lease->currency.' '.number_format(((int) $minor) / 100, 0, '.', ',');
        $daysLeft = $lease->daysLeft();
        $next = $lease->nextIncreaseOn();

        $lateFee = match ($lease->late_fee_type) {
            Lease::LATE_FEE_FIXED => $money($lease->late_fee_value),
            Lease::LATE_FEE_PERCENT => __(':pct% of the invoice', ['pct' => rtrim(rtrim(number_format(((int) $lease->late_fee_value) / 100, 2), '0'), '.')]),
            default => __('None'),
        };

        return [
            'lease' => $lease,
            'statusColor' => match ($lease->status) {
                Lease::STATUS_ACTIVE => 'success',
                Lease::STATUS_PENDING => 'warning',
                Lease::STATUS_TERMINATED => 'danger',
                default => 'gray',
            },
            'endingSoon' => $lease->isActive() && $daysLeft !== null && $daysLeft <= self::WARN_DAYS && $lease->renewal === null,
            'daysLeft' => $daysLeft,
            'terms' => array_filter([
                __('Dates') => $lease->start_date?->format('d/m/Y').' – '.($lease->end_date?->format('d/m/Y') ?? __('no end date')),
                __('Rent') => $lease->formatted_rent.' · '.mb_strtolower($lease->billing_cycle_label),
                __('Due') => __('Day :day of each period', ['day' => $lease->payment_due_day]),
                __('Grace period') => $lease->grace_days > 0 ? trans_choice(':count day, then overdue|:count days, then overdue', $lease->grace_days) : __('None: overdue the day after it is due'),
                __('Late fee') => $lateFee,
                __('Notice to leave') => $lease->notice_days ? trans_choice(':count day|:count days', $lease->notice_days) : __('Not set'),
                __('Rent increase') => $next !== null
                    ? __(':pct% every :months months', ['pct' => rtrim(rtrim((string) $lease->increase_percent, '0'), '.'), 'months' => $lease->increase_every_months ?: 12])
                    : __('None'),
                __('Next increase') => $next !== null ? __(':date → :rent', ['date' => $next->format('d/m/Y'), 'rent' => $money($lease->rentAfterIncrease())]) : null,
                __('Deposit') => $money($lease->deposit_amount).($lease->deposit_carried_over > 0 ? ' · '.__(':amount carried over', ['amount' => $money($lease->deposit_carried_over)]) : ''),
            ], fn (?string $value): bool => $value !== null),
            'renewalUrl' => $lease->renewal !== null ? LeaseResource::getUrl('view', ['record' => $lease->renewal]) : null,
            'renewedFromUrl' => $lease->renewedFrom !== null ? LeaseResource::getUrl('view', ['record' => $lease->renewedFrom]) : null,
            'history' => $lease->history()->with('user')->get(),
        ];
    }

    private function lease(): Lease
    {
        /** @var Lease $lease */
        $lease = $this->getRecord();

        return $lease;
    }
}
