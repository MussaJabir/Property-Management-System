<?php

use App\Http\Middleware\InitializeTenancyByUser;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load tenant routes here so they're registered synchronously
            // during the bootstrap phase rather than via the booted() callback
            // in TenancyServiceProvider (which can fire too late in tests).
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::group([], base_path('routes/tenant.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            // Global so Livewire AJAX POSTs (form submits) also get the
            // tenancy context set, not just full page loads through the
            // panel auth middleware. No-op if user not authenticated.
            InitializeTenancyByUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unknown tenant slug → friendly 404 with link back to the PMS landing.
        $exceptions->render(function (TenantCouldNotBeIdentifiedException $e, $request) {
            return response()->view('errors.client-not-found', [
                'slug' => $request->segment(1),
            ], 404);
        });
    })->create();
