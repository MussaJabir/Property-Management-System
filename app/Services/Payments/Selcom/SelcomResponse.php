<?php

declare(strict_types=1);

namespace App\Services\Payments\Selcom;

/**
 * Selcom's reply envelope: result, resultcode, message, reference, data.
 *
 * Result codes (Selcom docs, "API Response"): 000 success; 111 and 927 in
 * progress, query again later; 999 ambiguous, wait for reconciliation;
 * anything else failed.
 */
final class SelcomResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    public function __construct(
        public readonly string $result,
        public readonly string $resultCode,
        public readonly string $message,
        public readonly ?string $reference,
        public readonly array $data,
    ) {}

    /** @param  array<string, mixed>  $body */
    public static function fromArray(array $body): self
    {
        $data = $body['data'] ?? [];

        return new self(
            result: (string) ($body['result'] ?? ''),
            resultCode: (string) ($body['resultcode'] ?? ''),
            message: (string) ($body['message'] ?? ''),
            reference: isset($body['reference']) ? (string) $body['reference'] : null,
            data: is_array($data) ? array_values(array_filter($data, 'is_array')) : [],
        );
    }

    public function successful(): bool
    {
        return $this->resultCode === '000';
    }

    public function inProgress(): bool
    {
        return in_array($this->resultCode, ['111', '927'], true);
    }

    public function ambiguous(): bool
    {
        return $this->resultCode === '999';
    }

    /** Neither done nor failed yet: query again later. */
    public function unresolved(): bool
    {
        return $this->inProgress() || $this->ambiguous();
    }

    public function failed(): bool
    {
        return ! $this->successful() && ! $this->unresolved();
    }

    /** @return array<string, mixed> */
    public function first(): array
    {
        return $this->data[0] ?? [];
    }
}
