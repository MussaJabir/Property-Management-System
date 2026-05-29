<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Path-based identification: the first URL segment is the tenant slug.
| e.g. pms.bjptechnologies.co.tz/{tenant}/...
|
| We DON'T use `PreventAccessFromCentralDomains` here. That middleware
| protects subdomain/domain-based tenant routes from being accessed via
| the central domain — irrelevant for path-based tenancy where every
| tenant URL is served from the central domain by design.
|
| When we migrate to subdomain mode later, swap InitializeTenancyByPath
| for InitializeTenancyByDomain, drop the {tenant} prefix, and add
| PreventAccessFromCentralDomains back.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByPath::class,
])->prefix('/{tenant}')->group(function () {
    // Tenant public landing — replaced by CMS in Phase 9
    Route::get('/', function () {
        return 'Tenant workspace: '.tenant('id');
    });

    // /{tenant}/manage — operator panel (Filament wires this up in Phase 2)
    // /{tenant}/portal — renter portal (Livewire wires this up in Phase 8)
});
