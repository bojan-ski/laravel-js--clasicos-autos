<?php

use App\Http\Controllers\Listings\ListingController;
use Illuminate\Support\Facades\Route;

Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
