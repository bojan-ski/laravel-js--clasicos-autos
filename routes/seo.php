<?php

use App\Http\Controllers\Seo\SeoController;
use Illuminate\Support\Facades\Route;

Route::controller(SeoController::class)->group(function () {
    Route::get('/robots.txt', 'robots')->name('robots');
    Route::get('/sitemap.xml', 'sitemap')->name('sitemap');
});
