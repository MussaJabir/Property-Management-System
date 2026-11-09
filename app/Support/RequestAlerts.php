<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Client;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Who hears about a new request from the website, from the workspace's
 * Settings → Alerts: email to every staff member (on unless switched off)
 * and an SMS to the numbers the landlord lists (off until switched on).
 */
final class RequestAlerts
{
    public static function emailEnabled(Client $client): bool
    {
        return (bool) ($client->settings['request_email'] ?? true);
    }

    public static function smsEnabled(Client $client): bool
    {
        return (bool) ($client->settings['request_sms'] ?? false) && self::smsNumbers($client) !== [];
    }

    /** @return list<string> E.164 numbers */
    public static function smsNumbers(Client $client): array
    {
        $numbers = $client->settings['request_sms_numbers'] ?? [];

        return is_array($numbers) ? array_values(array_filter($numbers, 'is_string')) : [];
    }

    /** "0712345678" or "+255 712…" → "+255712345678"; null when not a valid number. */
    public static function normalise(string $phone): ?string
    {
        try {
            $number = new PhoneNumber(trim($phone), 'TZ');

            return $number->isValid() ? $number->formatE164() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** "+255712345678" → "0712 345 678" for screens and SMS. */
    public static function display(string $phone): string
    {
        try {
            return (new PhoneNumber($phone, 'TZ'))->formatNational();
        } catch (Throwable) {
            return $phone;
        }
    }
}
