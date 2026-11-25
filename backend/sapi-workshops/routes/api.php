<?php

declare(strict_types=1);

use App\Http\Controllers\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'UP']);

Route::get('/workshops', [WorkshopController::class, 'index']);
