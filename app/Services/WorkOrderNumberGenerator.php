<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Per-client, per-year work order numbers, issued when a maintenance request
 * is approved. Format: WO-{CLIENT_SLUG}-{YEAR}-{PADDED_NUMBER}.
 */
class WorkOrderNumberGenerator extends DocumentNumberGenerator
{
    protected function table(): string
    {
        return 'work_order_sequences';
    }

    protected function prefix(): string
    {
        return 'WO';
    }
}
