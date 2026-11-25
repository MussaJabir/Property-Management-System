<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Observers\ClientObserver;
use App\Observers\MaintenanceRequestObserver;
use App\Observers\PaymentObserver;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Support\PendingActivityLog;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // Safety net: never expose debug output (stack traces, environment,
        // Ignition) in production, even if APP_DEBUG is misconfigured on the
        // server. Force it off and flag the misconfiguration loudly.
        if ($this->app->environment('production') && config('app.debug')) {
            config(['app.debug' => false]);
            Log::critical('APP_DEBUG was enabled in production and has been forced off — fix the server environment.');
        }

        Client::observe(ClientObserver::class);
        Payment::observe(PaymentObserver::class);

        // Audit-log entries written outside a workspace request (daily jobs)
        // still belong to the record's workspace.
        PendingActivityLog::beforeLogging(function (ActivityContract $activity): void {
            if (! $activity instanceof Model) {
                return;
            }
            $subject = $activity->getRelationValue('subject');
            if (blank($activity->getAttribute('tenant_id')) && $subject instanceof Model && filled($subject->getAttribute('tenant_id'))) {
                $activity->setAttribute('tenant_id', $subject->getAttribute('tenant_id'));
            }
        });
        MaintenanceRequest::observe(MaintenanceRequestObserver::class);

        // A required dropdown has no ✕ to empty it: it must keep a value.
        Select::configureUsing(fn (Select $select) => $select
            ->selectablePlaceholder(fn (Select $component): bool => ! $component->isRequired()));

        // Pin Livewire's temporary upload location to the PRIVATE disk.
        // Without this it defaults to config('filesystems.default') which is
        // `public` in production — that both stores in-flight uploads in a
        // world-readable directory and makes behaviour disk-dependent. Temp
        // files are short-lived; they belong on the private disk. Filament
        // FileUpload fields then move the committed file to their own
        // (public/B2) disk on save.
        config([
            'livewire.temporary_file_upload.disk' => 'local',
            'livewire.temporary_file_upload.directory' => 'livewire-tmp',
        ]);
    }
}
