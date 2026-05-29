<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Client (tenant) Routes
|--------------------------------------------------------------------------
|
| Path-based identification: the first URL segment is the client slug.
| e.g. pms.bjptechnologies.co.tz/{client}/...
|
| File name stays `tenant.php` to match stancl/tenancy's middleware naming
| ("InitializeTenancyByPath") — see CLAUDE.md > Naming glossary.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByPath::class,
])->prefix('/{tenant}')->group(function () {
    Route::get('/', function () {
        $client = tenant();

        return view('clients.landing', [
            'client' => $client,
        ]);
    })->name('client.landing');

    // /{client}/manage — operator panel (Filament wires this up in Phase 2)
    // /{client}/portal — renter portal (Livewire wires this up in Phase 8)
});
