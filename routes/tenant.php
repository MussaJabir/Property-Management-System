<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| All tenant-scoped routes live here. Path-based identification: the first
| URL segment is the tenant slug, e.g. pms.bjptechnologies.co.tz/{tenant}/...
|
| Route group is wired up with the {tenant} parameter prefix. The
| InitializeTenancyByPath middleware resolves the slug to a Tenant model
| and initializes tenancy context for the request.
|
| When we move to subdomain mode later, swap InitializeTenancyByPath for
| InitializeTenancyByDomain and remove the {tenant} prefix.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByPath::class,
    PreventAccessFromCentralDomains::class,
])->prefix('/{tenant}')->group(function () {
    // Tenant public landing — replaced by CMS in Phase 9
    Route::get('/', function ($tenant) {
        return 'Tenant workspace: '.tenant('id');
    });

    // /{tenant}/manage — operator panel (Filament wires this up in Phase 2)
    // /{tenant}/portal — renter portal (Livewire wires this up in Phase 8)
});
