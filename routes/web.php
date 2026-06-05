<?php

use App\Http\Controllers\Operator\OperatorActivationController;
use App\Http\Controllers\Portal\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('locale', LocaleController::class)->name('locale.switch');

// Operator (staff) account activation — central, one-time link from the invite.
Route::get('staff/activate/{user}/{token}', [OperatorActivationController::class, 'show'])->name('operator.activate');
Route::post('staff/activate/{user}/{token}', [OperatorActivationController::class, 'store'])->name('operator.activate.store');
