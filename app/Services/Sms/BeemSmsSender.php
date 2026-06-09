<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends transactional SMS via Beem Africa (https://apisms.beem.africa).
 *
 * Best-effort by design: returns false (never throws) when Beem isn't
 * configured, the number is empty, or the request fails — so it can sit
 * alongside email delivery without ever breaking the calling flow.
 */
class BeemSmsSender
{
    public function send(string $phone, string $message): bool
    {
        $key = config('services.beem.api_key');
        $secret = config('services.beem.secret');

        if (! is_string($key) || $key === '' || ! is_string($secret) || $secret === '') {
            return false; // not configured — email path still applies
        }

        // Beem wants the MSISDN as digits only, e.g. 255712345678.
        $dest = preg_replace('/\D+/', '', $phone) ?? '';

        if ($dest === '') {
            return false;
        }

        try {
            return Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->asJson()
                ->post((string) config('services.beem.endpoint'), [
                    'source_addr' => (string) config('services.beem.source_addr'),
                    'encoding' => 0,
                    'message' => $message,
                    'recipients' => [
                        ['recipient_id' => 1, 'dest_addr' => $dest],
                    ],
                ])
                ->successful();
        } catch (Throwable $e) {
            Log::warning('Beem SMS send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
