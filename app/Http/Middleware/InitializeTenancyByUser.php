<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialize stancl/tenancy context from the authenticated user's tenant_id,
 * plus set Spatie Permission's current team to the same Client id.
 *
 * Used by the operator panel (and later the renter portal) where the URL
 * does not carry the tenant slug — we rely on auth instead.
 *
 * For path-based routes (e.g. /{slug}/...) use stancl's InitializeTenancyByPath
 * middleware instead; this one is for auth-scoped panels.
 */
class InitializeTenancyByUser
{
    public function __construct(
        protected Tenancy $tenancy,
        protected PermissionRegistrar $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $client = Client::find($user->tenant_id);

            if ($client) {
                $this->tenancy->initialize($client);
                $this->permissions->setPermissionsTeamId($client->id);
            }
        }

        return $next($request);
    }
}
