<?php

declare(strict_types=1);

namespace App\Services\Admin;

/**
 * Outcome of issuing an operator activation invite: the one-time link, and
 * whether the email carrying it actually left the application.
 */
final readonly class OperatorInvite
{
    public function __construct(
        public string $url,
        public bool $emailSent,
    ) {}
}
