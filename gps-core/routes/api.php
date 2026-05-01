<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('dev.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('dev.auth')->group(function () {
    Route::get('/tracking/current', [TrackingController::class, 'current']);
});


