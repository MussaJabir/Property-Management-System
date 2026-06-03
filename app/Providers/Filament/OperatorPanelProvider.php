<?php

namespace App\Providers\Filament;

use App\Filament\Operator\Pages\Auth\EditOperatorProfile;
use App\Filament\Operator\Widgets\CollectionsChartWidget;
use App\Filament\Operator\Widgets\GettingStartedWidget;
use App\Filament\Operator\Widgets\OccupancyChartWidget;
use App\Filament\Operator\Widgets\RecentPaymentsWidget;
use App\Filament\Operator\Widgets\TopUnpaidInvoicesWidget;
use App\Filament\Operator\Widgets\WorkspaceOverviewWidget;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\ForceOperatorPasswordChange;
use App\Http\Middleware\InitializeTenancyByUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Operator panel — for landlord staff (Owner, Manager, Accountant, Maintenance).
 *
 * Lives at /manage. Authentication uses the web guard against the User model
 * (filtered to type='operator' via User::canAccessPanel).
 *
 * InitializeTenancyByUser middleware (registered globally in bootstrap/app.php
 * on the web stack so it runs for both panel page loads AND Livewire AJAX
 * requests) reads $user->tenant_id and initializes stancl/tenancy context +
 * Spatie Permission team scope. Every Eloquent query, every role check, every
 * file upload — all automatically scoped to the operator's Client.
 *
 * URL design note: this panel does NOT carry the client slug in the URL
 * (e.g. /manage instead of /{slug}/manage). The user is authenticated and
 * belongs to exactly one Client, so URL-level scoping is redundant.
 * If/when we want branded URLs per client, swap to Filament's `->tenant()`
 * feature with slugAttribute='slug'.
 */
class OperatorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operator')
            ->path('manage')
            ->login()
            ->profile(EditOperatorProfile::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->brandName('PMS Operator')
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Filament/Operator/Resources'), for: 'App\\Filament\\Operator\\Resources')
            ->discoverPages(in: app_path('Filament/Operator/Pages'), for: 'App\\Filament\\Operator\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Operator/Widgets'), for: 'App\\Filament\\Operator\\Widgets')
            ->widgets([
                // Row 1: compact KPI cards. Row 2: collections + occupancy
                // charts side by side. Row 3: the two tables side by side.
                // Getting-started only renders until the workspace is set up.
                WorkspaceOverviewWidget::class,
                CollectionsChartWidget::class,
                OccupancyChartWidget::class,
                TopUnpaidInvoicesWidget::class,
                RecentPaymentsWidget::class,
                GettingStartedWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Initialize stancl/tenancy from the authenticated operator's
                // tenant_id. MUST be here: Filament panels use this explicit
                // middleware list, NOT Laravel's `web` group, so the global
                // web-append of this middleware in bootstrap/app.php does not
                // reach panel page requests. Without it the TenantScopedModel
                // global scope is inactive and operators see EVERY client's
                // data. Runs after Authenticate so $request->user() is set.
                InitializeTenancyByUser::class,
                // Block the panel when the client workspace is suspended
                // (runs after tenancy is initialized above).
                EnsureTenantActive::class,
                ForceOperatorPasswordChange::class,
            ]);
    }
}
