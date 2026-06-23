<?php

use App\Http\Controllers\Api\DeviceDataController;
use App\Http\Controllers\Api\Mobile\AlertController as MobileAlertController;
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\DashboardController as MobileDashboardController;
use App\Http\Controllers\Api\Mobile\DeviceController as MobileDeviceController;
use App\Http\Controllers\Api\Mobile\ExportController as MobileExportController;
use App\Http\Controllers\Api\Mobile\GeofenceController as MobileGeofenceController;
use App\Http\Controllers\Api\Mobile\LiveStreamController as MobileLiveStreamController;
use App\Http\Controllers\Api\Mobile\MapController as MobileMapController;
use App\Http\Controllers\Api\Mobile\ProfileController as MobileProfileController;
use App\Http\Controllers\Api\Mobile\PushTokenController as MobilePushTokenController;
use App\Http\Controllers\Api\UserDeviceController;
use Illuminate\Support\Facades\Route;

Route::post('/device/data', [DeviceDataController::class, 'receive']);

/*
|--------------------------------------------------------------------------
| End-user mobile app API (Sanctum)
|--------------------------------------------------------------------------
*/
Route::post('/login', [MobileAuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'mobile.end_user'])->group(function () {
    Route::post('/logout', [MobileAuthController::class, 'logout']);
    Route::post('/push-token', [MobilePushTokenController::class, 'store']);
    Route::delete('/push-token', [MobilePushTokenController::class, 'destroy']);
    Route::post('/push-test', [\App\Http\Controllers\Api\Mobile\PushTestController::class, 'send']);
});

Route::middleware([
    'auth:sanctum',
    'mobile.end_user',
    'mobile.entitlement',
])->group(function () {
    Route::get('/profile', [MobileProfileController::class, 'show']);
    Route::post('/profile/update', [MobileProfileController::class, 'update']);
    Route::post('/profile/avatar', [MobileProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [MobileProfileController::class, 'deleteAvatar']);
    Route::post('/change-password', [MobileProfileController::class, 'changePassword']);

    Route::get('/dashboard', [MobileDashboardController::class, 'summary']);
    Route::get('/dashboard/activity', [MobileDashboardController::class, 'activity']);
    Route::get('/dashboard/recent-vehicles', [MobileDashboardController::class, 'recentVehicles']);

    Route::get('/devices', [MobileDeviceController::class, 'index']);
    Route::get('/devices/{id}', [MobileDeviceController::class, 'show'])->whereNumber('id');
    Route::get('/devices/{id}/live', [MobileDeviceController::class, 'live'])->whereNumber('id');
    Route::get('/devices/{id}/history', [MobileDeviceController::class, 'history'])->whereNumber('id');
    Route::get('/devices/{id}/route-summary', [MobileDeviceController::class, 'routeSummary'])->whereNumber('id');
    Route::get('/devices/{id}/events', [MobileDeviceController::class, 'events'])->whereNumber('id');
    Route::get('/devices/{id}/live-stream', [MobileLiveStreamController::class, 'show'])->whereNumber('id');

    Route::get('/geofences', [MobileGeofenceController::class, 'index']);

    Route::get('/alerts', [MobileAlertController::class, 'index']);
    Route::get('/alerts/unread', [MobileAlertController::class, 'unread']);
    Route::post('/alerts/read', [MobileAlertController::class, 'markRead']);

    Route::get('/address', [MobileMapController::class, 'address']);

    Route::get('/export/csv', [MobileExportController::class, 'csv']);
    Route::get('/export/gpx', [MobileExportController::class, 'gpx']);
});

/*
|--------------------------------------------------------------------------
| Legacy Sanctum device API (unchanged)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my/devices', [UserDeviceController::class, 'list']);
    Route::get('/device/{imei}/latest', [UserDeviceController::class, 'latest']);
    Route::get('/device/{imei}/history', [UserDeviceController::class, 'history']);
});
