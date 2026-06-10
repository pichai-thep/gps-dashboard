<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ForbiddenZoneController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\MapLayerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PoiController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\VehicleManagementController;

// mobile
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileDeviceController;
use App\Http\Controllers\Api\MobileAlarmSettingController;

use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web API - ใช้กับ Vue Dashboard เดิม
|--------------------------------------------------------------------------
*/

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

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('/vehicles', [VehicleManagementController::class, 'index']);
    Route::get('/vehicles/{imei}', [VehicleManagementController::class, 'show']);
    Route::put('/vehicles/{imei}', [VehicleManagementController::class, 'updateVehicle']);
    Route::put('/vehicles/{imei}/mileage', [VehicleManagementController::class, 'updateMileage']);
    Route::put('/vehicles/{imei}/ur-rate', [VehicleManagementController::class, 'updateUrRate']);

    Route::get('/vehicle-groups', [VehicleManagementController::class, 'groups']);
    Route::post('/vehicle-groups', [VehicleManagementController::class, 'createGroup']);
    Route::delete('/vehicle-groups/{id}', [VehicleManagementController::class, 'deleteGroup']);
    Route::post('/vehicle-groups/move', [VehicleManagementController::class, 'moveToGroup']);
    Route::post('/vehicle-groups/remove-vehicles', [VehicleManagementController::class, 'removeVehiclesFromGroup']);

    Route::get('/stations', [StationController::class, 'index']);
    Route::post('/stations', [StationController::class, 'store']);
    Route::put('/stations/{id}', [StationController::class, 'update']);
    Route::delete('/stations/{id}', [StationController::class, 'destroy']);

    Route::get('/pois', [PoiController::class, 'index']);
    Route::post('/pois', [PoiController::class, 'store']);
    Route::put('/pois/{id}', [PoiController::class, 'update']);
    Route::delete('/pois/{id}', [PoiController::class, 'destroy']);

    Route::get('/forbidden-zones', [ForbiddenZoneController::class, 'index']);
    Route::post('/forbidden-zones', [ForbiddenZoneController::class, 'store']);
    Route::put('/forbidden-zones/{id}', [ForbiddenZoneController::class, 'update']);
    Route::delete('/forbidden-zones/{id}', [ForbiddenZoneController::class, 'destroy']);

    Route::get('/map-layers/pois', [MapLayerController::class, 'pois']);
    Route::get('/map-layers/stations', [MapLayerController::class, 'stations']);
    Route::get('/map-layers/forbidden-zones', [MapLayerController::class, 'forbiddenZones']);

    Route::get('/notifications/recent', [NotificationController::class, 'recent']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-read', [NotificationController::class, 'markRead']);

    Route::prefix('reports')->group(function () {
        Route::get('/daily-summary', [ReportController::class, 'dailySummary']);
        Route::get('/status-summary', [ReportController::class, 'statusSummary']);
        Route::get('/station-summary', [ReportController::class, 'stationSummary']);

        Route::get('/options/groups', [ReportController::class, 'groupOptions']);
        Route::get('/options/vehicles', [ReportController::class, 'vehicleOptions']);
        Route::get('/options/stations', [ReportController::class, 'stationOptions']);
    });
});


/*
|--------------------------------------------------------------------------
| Mobile API - Android / iOS
|--------------------------------------------------------------------------
*/

Route::prefix('mobile/v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/login', [MobileAuthController::class, 'login']);

        Route::middleware(['mobile.auth','gps'])->group(function () {
            Route::get('/me', [MobileAuthController::class, 'me']);
            Route::post('/logout', [MobileAuthController::class, 'logout']);

            Route::post('/devices/register', [MobileDeviceController::class, 'register']);
            Route::post('/devices/unregister', [MobileDeviceController::class, 'unregister']);

        });
    });


    Route::middleware(['mobile.auth', 'gps'])->group(function () {

        Route::get('/tracking/current', [TrackingController::class, 'current']);
        Route::get('/tracking/history', [HistoryController::class, 'index']);
        Route::get('/tracking/groups', [TrackingController::class, 'groups']);

        Route::get('/map-layers/pois', [MapLayerController::class, 'pois']);
        Route::get('/map-layers/stations', [MapLayerController::class, 'stations']);
        Route::get('/map-layers/forbidden-zones', [MapLayerController::class, 'forbiddenZones']);

        Route::get('/notifications/recent', [NotificationController::class, 'recent']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/mark-read', [NotificationController::class, 'markRead']);

        Route::get('/alarm-settings', [MobileAlarmSettingController::class, 'index']);
        Route::put('/alarm-settings', [MobileAlarmSettingController::class, 'update']);
    });
});
