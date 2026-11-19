<?php

use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\ConversationController;
use App\Http\Controllers\API\V1\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/user', [AuthController::class, 'user'])->name('auth.user');

        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');

        Route::post('/conversations', [ConversationController::class, 'store'])
            ->middleware('throttle:conversations')
            ->name('conversations.store');

        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index'])
            ->name('conversations.messages.index');

        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
            ->middleware('throttle:messages')
            ->name('conversations.messages.store');
    });
});
