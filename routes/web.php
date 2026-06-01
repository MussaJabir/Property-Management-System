<?php

use App\Http\Controllers\Portal\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('locale', LocaleController::class)->name('locale.switch');
