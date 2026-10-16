<?php

use App\Http\Controllers\Contact\ContactController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-profile'])->group(function () {
    Route::get('/contact', [ContactController::class, 'show'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
});
