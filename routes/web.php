<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Operator\OperatorActivationController;
use App\Http\Controllers\Portal\LocaleController;
use App\Http\Controllers\PwaController;
use App\Http\Middleware\EnsureTenantActive;
use App\Livewire\Marketplace\Browse;
use App\Livewire\Marketplace\Room;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::get('/', function () {
    return view('welcome', ['plans' => Plan::publicCatalogue()]);
})->name('home');

Route::post('locale', LocaleController::class)->name('locale.switch');

// Installable app (PWA). Registered before the /{tenant} routes so these
// single-segment paths aren't taken for a client slug.
Route::get('service-worker.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
Route::get('manage/manifest.webmanifest', [PwaController::class, 'operatorManifest'])->name('pwa.operator-manifest');
Route::get('.well-known/assetlinks.json', [PwaController::class, 'assetLinks'])->name('pwa.asset-links');

// PMS marketplace: vacant units from every client that listed them.
Route::get('browse', Browse::class)->name('marketplace.browse');

// One listed room inside the marketplace. It runs inside that client's
// workspace (like their own room page) so the booking form and WhatsApp
// work unchanged; the Livewire calls it makes re-run this middleware.
Route::get('browse/{tenant}/{unit}', Room::class)
    ->middleware([InitializeTenancyByPath::class, EnsureTenantActive::class])
    ->name('marketplace.room');

// Privacy policy (public; also the link the Play Store listing points to).
Route::view('privacy', 'privacy')->name('privacy');

// Onboarding tour: mark finished/skipped for the signed-in operator (web guard).
// No `auth` middleware needed — the controller stamps only the currently
// authenticated web user, so a guest request is a harmless no-op.
Route::post('manage/onboarding/complete', [OnboardingController::class, 'complete'])
    ->defaults('guard', 'web')
    ->name('operator.onboarding.complete');

// Operator (staff) account activation — central, one-time link from the invite.
Route::get('staff/activate/{user}/{token}', [OperatorActivationController::class, 'show'])->name('operator.activate');
Route::post('staff/activate/{user}/{token}', [OperatorActivationController::class, 'store'])->name('operator.activate.store');
