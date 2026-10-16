<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\Admin\ListingQueueController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::patch('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend')->whereNumber('user');
    Route::patch('/users/{user}/unsuspend', [UserController::class, 'unsuspend'])->name('users.unsuspend')->whereNumber('user');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->whereNumber('user');

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    Route::get('/listings', [ListingQueueController::class, 'index'])->name('listings');
    Route::patch('/listings/{listing}/promote-model', [ListingModerationController::class, 'promote'])->name('listings.promote')->whereNumber('listing');
    Route::patch('/listings/{listing}/approve', [ListingModerationController::class, 'approve'])->name('listings.approve')->whereNumber('listing');
    Route::patch('/listings/{listing}/reject', [ListingModerationController::class, 'reject'])->name('listings.reject')->whereNumber('listing');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::patch('/reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve')->whereNumber('report');

    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback');
    Route::patch('/feedback/{contactMessage}/status', [FeedbackController::class, 'updateStatus'])->name('feedback.status')->whereNumber('contactMessage');
});
