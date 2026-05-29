<?php

namespace App\Services;

/**
 * Per-tenant, per-year receipt number generator.
 * Format: RCP-{TENANT_SLUG}-{YEAR}-{PADDED_NUMBER}
 * Concurrency story: see DocumentNumberGenerator (advisory lock).
 */
class ReceiptNumberGenerator extends DocumentNumberGenerator
{
    protected function table(): string
    {
        return 'receipt_sequences';
    }

    protected function prefix(): string
    {
        return 'RCP';
    }
}
