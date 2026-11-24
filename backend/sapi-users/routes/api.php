<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'UP']);

Route::post('/users', [UserController::class, 'store']);
Route::get('/users/{userId}', [UserController::class, 'show']);
Route::patch('/users/{userId}', [UserController::class, 'update']);
Route::post('/authentications', [AuthenticationController::class, 'store']);
