<?php

declare(strict_types=1);

namespace App\Filament\Operator\Support;

use App\Models\Lease;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Rent is typed as a monthly amount, whatever the billing cycle, and saved
 * as the amount for one billing period (what each invoice charges). So a
 * 6-month room at 300,000 a month saves 1,800,000 and the form shows
 * "Every 6 months: TZS 1,800,000" under the field.
 *
 * `monthly_rent` is the field people see and is never saved; `rent_amount`
 * (cents, per period) is hidden and only recalculated when the monthly rent
 * or the cycle changes, so opening and saving a record never alters it.
 *
 * Forms using these must call sync() when the billing cycle fields change.
 */
final class RentFields
{
    /** @return array{0: TextInput, 1: Hidden} */
    public static function make(string $currencyField): array
    {
        return [
            TextInput::make('monthly_rent')
                ->label(__('Monthly rent'))
                ->prefix(fn (Get $get): string => $get($currencyField) ?? 'TZS')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(1)
                ->placeholder('300000')
                ->dehydrated(false)
                ->live(onBlur: true)
                ->afterStateHydrated(function (TextInput $component, Get $get): void {
                    $component->state(self::monthlyFrom($get('rent_amount'), self::months($get)));
                })
                ->afterStateUpdated(fn (Get $get, Set $set) => self::sync($get, $set))
                ->helperText(fn (Get $get): ?string => self::periodTotal($get, $get($currencyField) ?? 'TZS')),

            Hidden::make('rent_amount')
                ->default(0),
        ];
    }

    /** Recalculate the per-period rent from the monthly rent and the cycle. */
    public static function sync(Get $get, Set $set): void
    {
        $monthly = $get('monthly_rent');

        if ($monthly === null || $monthly === '' || ! is_numeric($monthly)) {
            return;
        }

        $set('rent_amount', (int) round(((float) $monthly) * 100 * self::months($get)));
    }

    /** Monthly rent in whole shillings, for a per-period amount in cents. */
    public static function monthlyFrom(mixed $periodCents, int $months): int|float|null
    {
        if (! is_numeric($periodCents) || (int) $periodCents <= 0) {
            return null;
        }

        $monthly = round(((int) $periodCents) / 100 / max(1, $months), 2);

        return floor($monthly) === $monthly ? (int) $monthly : $monthly;
    }

    /** Months in one billing period, from the form's cycle fields. */
    public static function months(Get $get): int
    {
        return self::monthsFor($get('billing_cycle'), $get('billing_cycle_months'));
    }

    /** Months in one billing period of $cycle (unit and lease cycles match). */
    public static function monthsFor(mixed $cycle, mixed $customMonths = null): int
    {
        return match ($cycle) {
            Lease::BILLING_QUARTERLY => 3,
            Lease::BILLING_SEMI_ANNUAL => 6,
            Lease::BILLING_ANNUAL => 12,
            Lease::BILLING_CUSTOM => max(1, (int) $customMonths),
            default => 1,
        };
    }

    private static function periodTotal(Get $get, string $currency): ?string
    {
        $months = self::months($get);
        $monthly = $get('monthly_rent');

        if ($months === 1 || ! is_numeric($monthly) || (float) $monthly <= 0) {
            return null;
        }

        $total = number_format(((float) $monthly) * $months, 0, '.', ',');

        return $months === 12
            ? __('Every year: :currency :total', ['currency' => $currency, 'total' => $total])
            : __('Every :months months: :currency :total', ['months' => $months, 'currency' => $currency, 'total' => $total]);
    }
}
