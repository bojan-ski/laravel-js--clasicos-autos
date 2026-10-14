<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ListingModerationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    Route::patch('/listings/{listing}/approve', [ListingModerationController::class, 'approve'])->name('listings.approve')->whereNumber('listing');
    Route::patch('/listings/{listing}/reject', [ListingModerationController::class, 'reject'])->name('listings.reject')->whereNumber('listing');
});
