<?php

use App\Http\Controllers\Compare\CompareController;
use Illuminate\Support\Facades\Route;

Route::get('/compare', [CompareController::class, 'show'])->name('compare.show');
Route::delete('/compare', [CompareController::class, 'clear'])->name('compare.clear');
Route::post('/compare/{listing}', [CompareController::class, 'add'])->whereNumber('listing')->name('compare.add');
Route::delete('/compare/{listing}', [CompareController::class, 'remove'])->whereNumber('listing')->name('compare.remove');
