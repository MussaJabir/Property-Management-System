<?php

use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Operator\OperatorActivationController;
use App\Http\Controllers\Portal\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('locale', LocaleController::class)->name('locale.switch');

// Onboarding tour: mark finished/skipped for the signed-in operator (web guard).
// No `auth` middleware needed — the controller stamps only the currently
// authenticated web user, so a guest request is a harmless no-op.
Route::post('manage/onboarding/complete', [OnboardingController::class, 'complete'])
    ->defaults('guard', 'web')
    ->name('operator.onboarding.complete');

// Operator (staff) account activation — central, one-time link from the invite.
Route::get('staff/activate/{user}/{token}', [OperatorActivationController::class, 'show'])->name('operator.activate');
Route::post('staff/activate/{user}/{token}', [OperatorActivationController::class, 'store'])->name('operator.activate.store');
