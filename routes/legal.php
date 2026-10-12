<?php

use App\Http\Controllers\Legal\CookieConsentController;
use App\Http\Controllers\Legal\LegalController;
use Illuminate\Support\Facades\Route;

Route::controller(LegalController::class)->group(function () {
    Route::get('/privacy-policy', 'privacy')->name('legal.privacy');
    Route::get('/terms', 'terms')->name('legal.terms');
    Route::get('/cookie-policy', 'cookies')->name('legal.cookies');
});

Route::post('/cookie-consent', CookieConsentController::class)->name('cookie-consent.store');
