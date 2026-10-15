<?php

use App\Http\Controllers\Conversations\ConversationController;
use App\Http\Controllers\Conversations\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::middleware('can:access-messaging')->group(function () {
        Route::get('/listings/{listing}/message', [ConversationController::class, 'create'])->whereNumber('listing')->name('conversations.create');
        Route::post('/listings/{listing}/message', [ConversationController::class, 'store'])->whereNumber('listing')->name('conversations.store');
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->whereNumber('conversation')->scopeBindings()->name('conversations.messages.store');
    });

    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('conversations.show');
    Route::delete('/conversations/{conversation}/messages/{message}', [MessageController::class, 'destroy'])
        ->whereNumber(['conversation', 'message'])->scopeBindings()->name('conversations.messages.destroy');
});
