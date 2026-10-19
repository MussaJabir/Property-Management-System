<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rent invoices for the current period, issued before the overdue check so
// a period that starts today isn't missed.
Schedule::command('billing:generate-rent-invoices')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->runInBackground();

// Daily overdue detection at 06:00 Africa/Dar_es_Salaam (config/app.php
// already sets the default timezone). withoutOverlapping protects against
// long runs being kicked off twice if the previous one hasn't finished.
Schedule::command('billing:detect-overdue')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Mobile money prompts still waiting on Selcom: Selcom asks for a status
// query every 3 minutes for unresolved transactions. Catches payments whose
// callback never arrived and expires prompts nobody paid.
Schedule::command('payments:check-selcom')
    ->everyThreeMinutes()
    ->withoutOverlapping()
    ->runInBackground();
