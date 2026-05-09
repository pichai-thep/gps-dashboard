<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['dev.auth', 'gps'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['dev.auth', 'gps'])->group(function () {
    Route::get('/tracking/current', [TrackingController::class, 'current']);
    Route::get('/tracking/groups', [TrackingController::class, 'groups']);

    Route::get('/tracking/history', [HistoryController::class, 'index']);
    Route::get('/tracking/history/export', [HistoryController::class, 'export']);
});

Route::get('/notifications/recent', [NotificationController::class, 'recent']);
