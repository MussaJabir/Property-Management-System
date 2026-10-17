<?php

declare(strict_types=1);

namespace App\Filament\Operator\Support;

use App\Models\Client;
use App\Services\Plans\PlanUsage;
use Illuminate\Support\Carbon;

/**
 * Short stays for the signed-in client: is the feature on their plan (Pro
 * and Enterprise; a client with no plan gets everything), and their
 * check-in / check-out times (Settings → Preferences).
 */
final class ShortStays
{
    public const DEFAULT_CHECK_IN = '14:00';

    public const DEFAULT_CHECK_OUT = '10:00';

    public static function enabled(): bool
    {
        return PlanUsage::forCurrentUser()?->hasFeature('short_stays') ?? true;
    }

    public static function checkInTime(?Client $client = null): string
    {
        return self::time($client, 'check_in_time', self::DEFAULT_CHECK_IN);
    }

    public static function checkOutTime(?Client $client = null): string
    {
        return self::time($client, 'check_out_time', self::DEFAULT_CHECK_OUT);
    }

    /** Has today's check-out time passed? Departures after it are late. */
    public static function pastCheckOut(): bool
    {
        return now()->gt(Carbon::parse(today()->toDateString().' '.self::checkOutTime()));
    }

    private static function time(?Client $client, string $key, string $default): string
    {
        $client ??= Client::find(auth()->user()?->tenant_id);
        $value = $client?->settings[$key] ?? null;

        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value : $default;
    }
}
