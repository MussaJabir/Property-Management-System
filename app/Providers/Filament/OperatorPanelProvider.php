<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Pages\Auth\EditOperatorProfile;
use App\Filament\Operator\Pages\Dashboard;
use App\Filament\Operator\Widgets\CollectionsChartWidget;
use App\Filament\Operator\Widgets\ComingUpWidget;
use App\Filament\Operator\Widgets\GettingStartedWidget;
use App\Filament\Operator\Widgets\NeedsAttentionWidget;
use App\Filament\Operator\Widgets\RecentPaymentsWidget;
use App\Filament\Operator\Widgets\WorkspaceOverviewWidget;
use App\Http\Controllers\Operator\ReceiptPdfController;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\ForceOperatorPasswordChange;
use App\Http\Middleware\InitializeTenancyByUser;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
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
            ->login(Login::class)
            ->profile(EditOperatorProfile::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->brandName(fn (): string => auth()->user()?->client?->name ?? 'PMS')
            ->brandLogo(fn (): Htmlable => view('filament.operator.brand'))
            ->brandLogoHeight('2.25rem')
            ->colors([
                // PMS teal (#0f766e) as the 600 shade: Tailwind's teal shifted
                // one step darker. Filament only gives buttons white text when
                // both 600 (button) and 500 (hover) pass WCAG AA against white,
                // so 500 sits between teal-600 and teal-700 (contrast 4.7).
                'primary' => array_replace(Color::Teal, [
                    500 => 'oklch(0.54 0.103 185.8)',
                    600 => Color::Teal[700],
                    700 => Color::Teal[800],
                    800 => Color::Teal[900],
                    900 => Color::Teal[950],
                    950 => 'oklch(0.22 0.04 193)',
                ]),
                // Neutral greys with a faint teal cast, so surfaces read clean.
                'gray' => Color::generatePalette('#6b7575'),
            ])
            ->font('Geist', provider: GoogleFontProvider::class)
            ->viteTheme('resources/css/filament/operator/theme.css')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('15rem')
            ->navigationGroups(NavGroup::class)
            ->globalSearchKeyBindings(['/', 'mod+k'])
            // The menu was regrouped (Oct 2026). Browsers remember folded
            // groups by label, so clear that memory once to show the new
            // defaults. Runs before Alpine reads it.
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): Htmlable => new HtmlString("<script>try{if(localStorage.getItem('pms-nav-layout')!=='2026-10'){localStorage.removeItem('collapsedGroups');localStorage.setItem('pms-nav-layout','2026-10');}}catch(e){}</script>"),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): Htmlable => view('filament.operator.plan-usage'),
            )
            ->discoverResources(in: app_path('Filament/Operator/Resources'), for: 'App\\Filament\\Operator\\Resources')
            ->discoverPages(in: app_path('Filament/Operator/Pages'), for: 'App\\Filament\\Operator\\Pages')
            ->pages([
                Dashboard::class,
            ])
            // First-login onboarding tour (driver.js). Injected at body end so
            // the sidebar nav it spotlights is already in the DOM.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): Htmlable => view('filament.operator.onboarding-tour'),
            )
            ->userMenuItems([
                MenuItem::make()
                    ->label(__('common.onboarding.replay'))
                    ->icon(Heroicon::OutlinedMap)
                    ->url(fn (): string => Dashboard::getUrl().'?tour=replay'),
            ])
            ->discoverWidgets(in: app_path('Filament/Operator/Widgets'), for: 'App\\Filament\\Operator\\Widgets')
            ->widgets([
                // Today: key figures, then the work lists (needs attention +
                // coming up), then billed vs collected + latest payments.
                // Getting-started only renders until the workspace is set up.
                WorkspaceOverviewWidget::class,
                NeedsAttentionWidget::class,
                ComingUpWidget::class,
                CollectionsChartWidget::class,
                RecentPaymentsWidget::class,
                GettingStartedWidget::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('receipts/{receipt}/pdf', ReceiptPdfController::class)->name('receipts.pdf');
            })
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
