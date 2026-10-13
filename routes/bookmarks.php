<?php

use App\Http\Controllers\Bookmarks\BookmarkController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/bookmarks/{listing}', [BookmarkController::class, 'store'])->whereNumber('listing')->name('bookmarks.store');
    Route::delete('/bookmarks/{listing}', [BookmarkController::class, 'destroy'])->whereNumber('listing')->name('bookmarks.destroy');
});
