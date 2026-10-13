<?php

use App\Http\Controllers\Users\SellerListingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/users/{user}/listings', [SellerListingController::class, 'index'])->whereNumber('user')->name('users.listings');
});
