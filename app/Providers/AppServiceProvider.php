<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Payment;
use App\Observers\ClientObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Client::observe(ClientObserver::class);
        Payment::observe(PaymentObserver::class);
    }
}
