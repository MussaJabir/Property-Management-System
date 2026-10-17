<?php

declare(strict_types=1);

namespace App\Filament\Operator\Support;

use App\Services\Plans\PlanUsage;

/**
 * Is the short stays / front desk feature on the signed-in client's plan?
 * (Pro and Enterprise; a client with no plan gets everything.)
 */
final class ShortStays
{
    public static function enabled(): bool
    {
        return PlanUsage::forCurrentUser()?->hasFeature('short_stays') ?? true;
    }
}
