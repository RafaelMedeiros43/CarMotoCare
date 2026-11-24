<?php

use App\Http\Controllers\MeController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'UP']);

// Public
Route::post('/users', [UserController::class, 'store']);
Route::post('/auth/tokens', [TokenController::class, 'store']);

// Needs a valid JWT
Route::middleware('auth.jwt')->group(function () {
    Route::get('/users/me', [MeController::class, 'show']);
    Route::patch('/users/me', [MeController::class, 'update']);
});
