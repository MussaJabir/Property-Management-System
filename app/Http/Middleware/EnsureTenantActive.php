<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block access when the current tenant (Client) is suspended.
 *
 * Runs AFTER tenancy is initialized — by InitializeTenancyByUser on the
 * operator panel, or by InitializeTenancyByPath on the public CMS site and
 * renter portal. If the resolved client is suspended, render a friendly
 * "workspace suspended" page (403) instead of letting staff or visitors in.
 *
 * Suspension is the enforcement arm of subscription/billing: a non-paying
 * client is cut off here.
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;

        if ($tenant instanceof Client && $tenant->isSuspended()) {
            return response()->view('errors.client-suspended', [
                'clientName' => $tenant->name,
                'contactEmail' => $tenant->contact_email,
            ], 403);
        }

        return $next($request);
    }
}
