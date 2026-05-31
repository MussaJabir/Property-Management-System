<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Observers\ClientObserver;
use App\Observers\MaintenanceRequestObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        Client::observe(ClientObserver::class);
        Payment::observe(PaymentObserver::class);
        MaintenanceRequest::observe(MaintenanceRequestObserver::class);
    }
}
