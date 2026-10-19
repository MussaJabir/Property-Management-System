<?php

declare(strict_types=1);

namespace App\Services\Payments\Selcom;

use App\Exceptions\SelcomUnavailable;
use App\Models\SelcomAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Signed calls to the Selcom Checkout API with one client's credentials.
 *
 * Every request carries (Selcom docs, "Authentication"):
 *   Authorization  SELCOM base64(api_key)
 *   Timestamp      ISO 8601, e.g. 2019-02-26T09:30:46+03:00
 *   Digest-Method  HS256
 *   Digest         base64(HMAC-SHA256("timestamp=T&f1=v1&f2=v2", api_secret))
 *   Signed-Fields  f1,f2 — every request field, in payload order
 *
 * This matches Selcom's own PHP client (selcom/selcom-apigw-client), which
 * signs all fields in order. GET and DELETE send the fields as the query.
 */
class SelcomClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $vendor,
    ) {}

    public static function for(SelcomAccount $account): self
    {
        if (! $account->hasCredentials()) {
            throw SelcomUnavailable::notConfigured();
        }

        $baseUrl = $account->baseUrl();

        if ($baseUrl === null) {
            throw SelcomUnavailable::noAddress($account->mode);
        }

        return new self($baseUrl, (string) $account->api_key, (string) $account->api_secret, (string) $account->vendor);
    }

    public function vendor(): string
    {
        return $this->vendor;
    }

    /**
     * Create an order for mobile money (no cards). URLs must be base64
     * encoded, which this does for `webhook`, `redirect_url`, `cancel_url`.
     *
     * @param  array<string, string|int>  $order  order_id, buyer_email, buyer_name, buyer_phone, amount, currency, no_of_items, …
     */
    public function createOrderMinimal(array $order): SelcomResponse
    {
        foreach (['webhook', 'redirect_url', 'cancel_url'] as $url) {
            if (isset($order[$url])) {
                $order[$url] = base64_encode((string) $order[$url]);
            }
        }

        return $this->post('/v1/checkout/create-order-minimal', ['vendor' => $this->vendor] + $order);
    }

    /** Send the push USSD prompt for an order to a mobile money number. */
    public function walletPayment(string $transid, string $orderId, string $msisdn): SelcomResponse
    {
        return $this->post('/v1/checkout/wallet-payment', [
            'transid' => $transid,
            'order_id' => $orderId,
            'msisdn' => $msisdn,
        ]);
    }

    public function orderStatus(string $orderId): SelcomResponse
    {
        return $this->send('get', '/v1/checkout/order-status', ['order_id' => $orderId]);
    }

    public function cancelOrder(string $orderId): SelcomResponse
    {
        return $this->send('delete', '/v1/checkout/cancel-order', ['order_id' => $orderId]);
    }

    /** Orders created between two dates (YYYY-MM-DD), inclusive. */
    public function listOrders(Carbon $from, Carbon $to): SelcomResponse
    {
        return $this->send('get', '/v1/checkout/list-orders', [
            'fromdate' => $from->toDateString(),
            'todate' => $to->toDateString(),
        ]);
    }

    /**
     * The auth headers for a request with these fields.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    public function headers(array $fields, ?Carbon $at = null): array
    {
        $timestamp = ($at ?? Carbon::now('Africa/Dar_es_Salaam'))->toIso8601String();

        $signing = 'timestamp='.$timestamp;
        foreach ($fields as $key => $value) {
            $signing .= '&'.$key.'='.self::stringValue($value);
        }

        return [
            'Authorization' => 'SELCOM '.base64_encode($this->apiKey),
            'Timestamp' => $timestamp,
            'Digest-Method' => 'HS256',
            'Digest' => base64_encode(hash_hmac('sha256', $signing, $this->apiSecret, true)),
            'Signed-Fields' => implode(',', array_keys($fields)),
        ];
    }

    /** @param  array<string, mixed>  $fields */
    private function post(string $path, array $fields): SelcomResponse
    {
        return $this->send('post', $path, $fields);
    }

    /** @param  array<string, mixed>  $fields */
    private function send(string $method, string $path, array $fields): SelcomResponse
    {
        $request = $this->request($fields);
        $url = $this->baseUrl.$path;

        try {
            $response = match ($method) {
                'post' => $request->post($url, $fields),
                'get' => $request->withQueryParameters($fields)->get($url),
                'delete' => $request->withQueryParameters($fields)->delete($url),
                default => throw new \InvalidArgumentException($method),
            };
        } catch (ConnectionException $e) {
            throw SelcomUnavailable::unreachable($e->getMessage());
        }

        return $this->parse($response);
    }

    /** @param  array<string, mixed>  $fields */
    private function request(array $fields): PendingRequest
    {
        return Http::withHeaders($this->headers($fields))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.selcom.timeout', 30));
    }

    private function parse(Response $response): SelcomResponse
    {
        $body = $response->json();

        // Selcom sends its envelope with failures too, so only a reply
        // without one is unusable.
        if (! is_array($body) || ! array_key_exists('resultcode', $body)) {
            throw SelcomUnavailable::badReply($response->status());
        }

        return SelcomResponse::fromArray($body);
    }

    private static function stringValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
