<?php

use App\Http\Controllers\DeadlineController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'UP']);

Route::get('/vehicles', [VehicleController::class, 'index']);
Route::post('/vehicles', [VehicleController::class, 'store']);

// whereUuid: an id that is not a UUID is a 404, not a database error.
Route::whereUuid(['vehicleId', 'maintenanceId', 'partId'])->group(function () {
    Route::get('/vehicles/{vehicleId}', [VehicleController::class, 'show']);
    Route::patch('/vehicles/{vehicleId}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{vehicleId}', [VehicleController::class, 'destroy']);

    Route::get('/vehicles/{vehicleId}/deadlines', [DeadlineController::class, 'index']);
    Route::post('/vehicles/{vehicleId}/deadlines', [DeadlineController::class, 'store']);

    Route::get('/vehicles/{vehicleId}/maintenances', [MaintenanceController::class, 'index']);
    Route::post('/vehicles/{vehicleId}/maintenances', [MaintenanceController::class, 'store']);
    Route::get('/vehicles/{vehicleId}/maintenances/{maintenanceId}', [MaintenanceController::class, 'show']);
    Route::patch('/vehicles/{vehicleId}/maintenances/{maintenanceId}', [MaintenanceController::class, 'update']);

    Route::get('/vehicles/{vehicleId}/expenses', [ExpenseController::class, 'index']);

    Route::get('/vehicles/{vehicleId}/parts', [PartController::class, 'index']);
    Route::patch('/vehicles/{vehicleId}/parts/{partId}', [PartController::class, 'update']);
});

// Pending deadlines of every vehicle (for the alerts microservice; no X-User-Id).
Route::get('/deadlines', [DeadlineController::class, 'pending']);
