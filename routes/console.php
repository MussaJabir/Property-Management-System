<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily overdue detection at 06:00 Africa/Dar_es_Salaam (config/app.php
// already sets the default timezone). withoutOverlapping protects against
// long runs being kicked off twice if the previous one hasn't finished.
Schedule::command('billing:detect-overdue')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();
