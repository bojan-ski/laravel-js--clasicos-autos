<?php

use App\Http\Controllers\Listings\ListingController;
use App\Http\Controllers\Listings\ListingImageController;
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

Route::middleware('auth')->group(function () {
    Route::get('/listings/{listing}/edit', [ListingManagementController::class, 'edit'])->whereNumber('listing')->name('listings.edit');
    Route::put('/listings/{listing}', [ListingManagementController::class, 'update'])->whereNumber('listing')->name('listings.update');
    Route::delete('/listings/{listing}', [ListingManagementController::class, 'destroy'])->whereNumber('listing')->name('listings.destroy');

    Route::get('/listings/{listing}/images', [ListingImageController::class, 'edit'])->whereNumber('listing')->name('listings.images.edit');
    Route::post('/listings/{listing}/images', [ListingImageController::class, 'store'])->whereNumber('listing')->name('listings.images.store');
    Route::patch('/listings/{listing}/images/{image}/primary', [ListingImageController::class, 'primary'])
        ->whereNumber(['listing', 'image'])->scopeBindings()->name('listings.images.primary');
    Route::delete('/listings/{listing}/images/{image}', [ListingImageController::class, 'destroy'])
        ->whereNumber(['listing', 'image'])->scopeBindings()->name('listings.images.destroy');
});
