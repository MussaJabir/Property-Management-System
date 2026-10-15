<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Client;
use App\Services\Plans\PlanUsage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404 a route group when the current client's plan does not include the
 * named feature, e.g. `EnsurePlanFeature::class.':renter_portal'`.
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $client = tenancy()->tenant;

        abort_if($client instanceof Client && ! PlanUsage::for($client)->hasFeature($feature), 404);

        return $next($request);
    }
}
