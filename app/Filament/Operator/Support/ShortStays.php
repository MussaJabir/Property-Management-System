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

    /** Check-in time as people read it, e.g. "2:00 PM". */
    public static function checkInLabel(?Client $client = null): string
    {
        return self::label(self::checkInTime($client));
    }

    /** Check-out time as people read it, e.g. "10:00 AM". */
    public static function checkOutLabel(?Client $client = null): string
    {
        return self::label(self::checkOutTime($client));
    }

    /** A saved "14:00" shown as "2:00 PM". */
    public static function label(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i', $time)->format('g:i A');
        } catch (\Throwable) {
            return $time;
        }
    }

    /**
     * Times to pick from, every half hour, saved as "14:00" and shown as
     * "2:00 PM". A saved time off the half hour stays in the list.
     *
     * @return array<string, string>
     */
    public static function timeOptions(?string $current = null): array
    {
        $options = [];
        for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $options[$time] = self::label($time);
        }

        if ($current !== null && preg_match('/^\d{2}:\d{2}$/', $current) === 1 && ! isset($options[$current])) {
            $options[$current] = self::label($current);
            ksort($options);
        }

        return $options;
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
