<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\ListingModerationController;
use App\Http\Controllers\Admin\ListingQueueController;
use App\Http\Controllers\Admin\MakerController;
use App\Http\Controllers\Admin\MakerModelController;
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

    Route::get('/makers', [MakerController::class, 'index'])->name('makers');
    Route::post('/makers', [MakerController::class, 'store'])->name('makers.store');
    Route::get('/makers/{maker}', [MakerController::class, 'show'])->name('makers.show')->whereNumber('maker');
    Route::patch('/makers/{maker}', [MakerController::class, 'update'])->name('makers.update')->whereNumber('maker');
    Route::patch('/makers/{maker}/active', [MakerController::class, 'setActive'])->name('makers.active')->whereNumber('maker');
    Route::delete('/makers/{maker}', [MakerController::class, 'destroy'])->name('makers.destroy')->whereNumber('maker');

    Route::post('/makers/{maker}/models', [MakerModelController::class, 'store'])->name('makers.models.store')->whereNumber('maker');
    Route::patch('/makers/{maker}/models/{model}', [MakerModelController::class, 'update'])->name('makers.models.update')->whereNumber(['maker', 'model'])->scopeBindings();
    Route::patch('/makers/{maker}/models/{model}/active', [MakerModelController::class, 'setActive'])->name('makers.models.active')->whereNumber(['maker', 'model'])->scopeBindings();
    Route::delete('/makers/{maker}/models/{model}', [MakerModelController::class, 'destroy'])->name('makers.models.destroy')->whereNumber(['maker', 'model'])->scopeBindings();
});
