<?php

namespace App\Services;

/**
 * Per-tenant, per-year invoice number generator.
 * Format: INV-{TENANT_SLUG}-{YEAR}-{PADDED_NUMBER}
 * Concurrency story: see DocumentNumberGenerator (advisory lock).
 */
class InvoiceNumberGenerator extends DocumentNumberGenerator
{
    protected function table(): string
    {
        return 'invoice_sequences';
    }

    protected function prefix(): string
    {
        return 'INV';
    }
}
