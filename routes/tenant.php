<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\LocaleController;
use App\Http\Controllers\Portal\LogoutController;
use App\Http\Controllers\Portal\ReceiptDownloadController;
use App\Http\Middleware\EnsureRenterAuthenticated;
use App\Livewire\Portal\Auth\Login;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\Invoices\Index as InvoicesIndex;
use App\Livewire\Portal\Maintenance\Create as MaintenanceCreate;
use App\Livewire\Portal\Maintenance\Index as MaintenanceIndex;
use App\Livewire\Portal\Maintenance\Show as MaintenanceShow;
use App\Livewire\Portal\Profile;
use App\Livewire\Public\Page as PublicPage;
use App\Livewire\Public\Units as PublicUnits;
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
    /* ----- Public CMS site (Phase 9) ----- */
    Route::get('/', PublicPage::class)->defaults('slug', 'home')->name('public.home');
    Route::get('/about', PublicPage::class)->defaults('slug', 'about')->name('public.about');
    Route::get('/units', PublicUnits::class)->name('public.units');
    Route::get('/news', PublicPage::class)->defaults('slug', 'news')->name('public.news');
    Route::get('/contact', PublicPage::class)->defaults('slug', 'contact')->name('public.contact');

    /* ----- Renter portal (Phase 8) ----- */
    Route::prefix('portal')->name('portal.')->group(function () {
        // Guests
        Route::get('login', Login::class)->name('login');

        // Authenticated renters
        Route::middleware([EnsureRenterAuthenticated::class])->group(function () {
            Route::get('/', Dashboard::class)->name('dashboard');
            Route::get('invoices', InvoicesIndex::class)->name('invoices.index');
            Route::get('invoices/{invoice}/receipt/{receipt}', ReceiptDownloadController::class)->name('invoices.receipt');
            Route::get('maintenance', MaintenanceIndex::class)->name('maintenance.index');
            Route::get('maintenance/create', MaintenanceCreate::class)->name('maintenance.create');
            Route::get('maintenance/{request}', MaintenanceShow::class)->name('maintenance.show');
            Route::get('profile', Profile::class)->name('profile');
            Route::post('locale', LocaleController::class)->name('locale.switch');
            Route::post('logout', LogoutController::class)->name('logout');
        });
    });
});
