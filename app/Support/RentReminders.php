<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;

/**
 * How many days before an invoice is due the renter gets a reminder.
 * Settings → Billing; 0 turns it off.
 */
final class RentReminders
{
    public const SETTING = 'rent_reminder_days';

    public const DEFAULT_DAYS = 3;

    public static function daysFor(?Client $client): int
    {
        return (int) ($client?->settings[self::SETTING] ?? self::DEFAULT_DAYS);
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return [
            0 => __('Do not send'),
            1 => __('1 day before'),
            3 => __('3 days before'),
            5 => __('5 days before'),
            7 => __('7 days before'),
        ];
    }
}
