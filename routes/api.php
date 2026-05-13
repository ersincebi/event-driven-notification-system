<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ObservabilityController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function () {
    Route::post('/', [NotificationController::class, 'store']);
    Route::post('/batch', [NotificationController::class, 'storeBatch']);
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/{id}', [NotificationController::class, 'show']);
    Route::get('/batch/{batchId}', [NotificationController::class, 'showBatch']);
    Route::patch('/{id}/cancel', [NotificationController::class, 'cancel']);
});

Route::prefix('system')->group(function () {
    Route::get('/metrics', [ObservabilityController::class, 'metrics']);
    Route::get('/health', [ObservabilityController::class, 'health']);
});
