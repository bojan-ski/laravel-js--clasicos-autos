<?php

use App\Http\Controllers\Listings\ListingController;
use App\Http\Controllers\Listings\ListingManagementController;
use App\Models\CarListing;
use Illuminate\Support\Facades\Route;

Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
Route::get('/listings/advanced', [ListingController::class, 'advanced'])->name('listings.advanced');

Route::middleware(['auth', 'can:access-profile'])->group(function () {
    Route::get('/listings/create', [ListingManagementController::class, 'create'])->can('create', CarListing::class)->name('listings.create');
    Route::post('/listings', [ListingManagementController::class, 'store'])->can('create', CarListing::class)->name('listings.store');
});

Route::get('/listings/{listing}', [ListingController::class, 'show'])->whereNumber('listing')->name('listings.show');
