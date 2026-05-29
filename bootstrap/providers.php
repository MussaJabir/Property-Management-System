<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\OperatorPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    OperatorPanelProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
    TenancyServiceProvider::class,
];
