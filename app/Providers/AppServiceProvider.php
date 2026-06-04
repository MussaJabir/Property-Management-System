<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Observers\ClientObserver;
use App\Observers\MaintenanceRequestObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

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
        Client::observe(ClientObserver::class);
        Payment::observe(PaymentObserver::class);
        MaintenanceRequest::observe(MaintenanceRequestObserver::class);

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
