<?php

use App\Http\Controllers\Listings\ListingController;
use Illuminate\Support\Facades\Route;

Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
Route::get('/listings/advanced', [ListingController::class, 'advanced'])->name('listings.advanced');
Route::get('/listings/{listing}', [ListingController::class, 'show'])->whereNumber('listing')->name('listings.show');
