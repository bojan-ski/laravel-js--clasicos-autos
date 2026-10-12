<?php

use App\Http\Controllers\Account\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-profile'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
