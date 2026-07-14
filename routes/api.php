<?php

use App\Http\Controllers\Api\DeviceDataController;
use App\Http\Controllers\Api\Mobile\AlertController as MobileAlertController;
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\CommandController as MobileCommandController;
use App\Http\Controllers\Api\Mobile\DashboardController as MobileDashboardController;
use App\Http\Controllers\Api\Mobile\DeviceController as MobileDeviceController;
use App\Http\Controllers\Api\Mobile\ExportController as MobileExportController;
use App\Http\Controllers\Api\Mobile\FleetController as MobileFleetController;
use App\Http\Controllers\Api\Mobile\GeofenceController as MobileGeofenceController;
use App\Http\Controllers\Api\Mobile\LiveStreamController as MobileLiveStreamController;
use App\Http\Controllers\Api\Mobile\MapController as MobileMapController;
use App\Http\Controllers\Api\Mobile\NotificationPreferenceController as MobileNotificationPreferenceController;
use App\Http\Controllers\Api\Mobile\ProfileController as MobileProfileController;
use App\Http\Controllers\Api\Mobile\PushTokenController as MobilePushTokenController;
use App\Http\Controllers\Api\Mobile\ReportController as MobileReportController;
use App\Http\Controllers\Api\Mobile\TrackingSettingsController as MobileTrackingSettingsController;
use App\Http\Controllers\Api\UserDeviceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

Route::post('/device/data', [DeviceDataController::class, 'receive'])
    ->middleware('throttle:device-ingest');

Route::match(['get', 'post'], '/traccar/forward', [\App\Http\Controllers\Api\TraccarForwardController::class, 'receive'])
    ->middleware('throttle:traccar-forward');

/*
|--------------------------------------------------------------------------
| End-user mobile app API (Sanctum)
|--------------------------------------------------------------------------
*/
Route::post('/login', [MobileAuthController::class, 'login'])
    ->middleware(['throttle:mobile-login', 'mobile.app_version']);

Route::middleware(['auth:sanctum', 'mobile.app_user', 'mobile.app_version'])->group(function () {
    Route::post('/logout', [MobileAuthController::class, 'logout']);
    Route::post('/push-token', [MobilePushTokenController::class, 'store']);
    Route::delete('/push-token', [MobilePushTokenController::class, 'destroy']);
    Route::post('/push-test', [\App\Http\Controllers\Api\Mobile\PushTestController::class, 'send']);

    // Private channel authorization for the mobile app (Sanctum bearer token).
    // The web app uses the session-guarded /broadcasting/auth; the mobile app
    // authenticates with a token, so it needs this token-guarded endpoint for
    // Reverb private-channel subscriptions (device.{id}) to authorize. Channel
    // rules live in routes/channels.php.
    Route::post('/broadcasting/auth', [
        \Illuminate\Broadcasting\BroadcastController::class,
        'authenticate',
    ]);
});

Route::middleware([
    'auth:sanctum',
    'mobile.app_user',
    'mobile.entitlement',
    'mobile.app_version',
])->group(function () {
    Route::get('/profile', [MobileProfileController::class, 'show']);
    Route::post('/profile/update', [MobileProfileController::class, 'update']);
    Route::post('/profile/avatar', [MobileProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [MobileProfileController::class, 'deleteAvatar']);
    Route::post('/change-password', [MobileProfileController::class, 'changePassword']);

    Route::middleware('permission:mobile.nav.home')->group(function () {
        Route::get('/dashboard/home', [MobileDashboardController::class, 'home']);
        Route::get('/dashboard', [MobileDashboardController::class, 'summary']);
        Route::get('/dashboard/activity', [MobileDashboardController::class, 'activity']);
        Route::get('/dashboard/recent-vehicles', [MobileDashboardController::class, 'recentVehicles']);
    });

    Route::get('/devices', [MobileDeviceController::class, 'index'])
        ->middleware('permission:mobile.map.open');
    Route::get('/fleet/live', [MobileFleetController::class, 'live'])
        ->middleware('permission:mobile.map.open');
    Route::get('/company-map-card', [MobileTrackingSettingsController::class, 'companyMapCard'])
        ->middleware('permission:mobile.map.open');
    Route::get('/devices/{id}', [MobileDeviceController::class, 'show'])
        ->middleware('permission:mobile.map.open')
        ->whereNumber('id');
    Route::get('/devices/{id}/live', [MobileDeviceController::class, 'live'])
        ->middleware('permission:mobile.map.open')
        ->whereNumber('id');
    Route::post('/devices/{id}/complete-trip', [MobileDeviceController::class, 'completeTrip'])
        ->middleware('permission:mobile.map.route_progress')
        ->whereNumber('id');
    Route::post('/devices/{id}/start-new-trip', [MobileDeviceController::class, 'startNewTrip'])
        ->middleware('permission:mobile.map.route_progress')
        ->whereNumber('id');
    Route::get('/devices/{id}/history', [MobileDeviceController::class, 'history'])
        ->middleware('permission:mobile.nav.history')
        ->whereNumber('id');
    Route::get('/devices/{id}/route-summary', [MobileDeviceController::class, 'routeSummary'])
        ->middleware('permission:mobile.map.route_progress')
        ->whereNumber('id');
    Route::get('/devices/{id}/events', [MobileDeviceController::class, 'events'])
        ->middleware('permission:mobile.nav.notifications')
        ->whereNumber('id');
    Route::get('/map-marker-options', [MobileDeviceController::class, 'mapAppearanceOptions'])
        ->middleware('permission:mobile.map.custom_icon');
    Route::patch('/devices/{id}/map-appearance', [MobileDeviceController::class, 'updateMapAppearance'])
        ->middleware('permission:mobile.map.custom_icon')
        ->whereNumber('id');
    Route::post('/devices/{id}/map-custom-icon', [MobileDeviceController::class, 'uploadMapCustomIcon'])
        ->middleware('permission:mobile.map.custom_icon')
        ->whereNumber('id');
    Route::delete('/devices/{id}/map-custom-icon', [MobileDeviceController::class, 'deleteMapCustomIcon'])
        ->middleware('permission:mobile.map.custom_icon')
        ->whereNumber('id');
    Route::get('/devices/{id}/live-stream', [MobileLiveStreamController::class, 'show'])
        ->middleware('permission:mobile.map.open')
        ->whereNumber('id');

    Route::middleware('permission:mobile.nav.commands')->group(function () {
        Route::get('/devices/{id}/commands', [MobileCommandController::class, 'index'])->whereNumber('id');
        Route::post('/devices/{id}/commands', [MobileCommandController::class, 'store'])->whereNumber('id');
        Route::delete('/devices/{id}/commands/{command}', [MobileCommandController::class, 'destroy'])
            ->whereNumber('id')
            ->whereNumber('command');
    });

    Route::get('/geofences', [MobileGeofenceController::class, 'index'])
        ->middleware('permission:mobile.map.open');

    Route::middleware('permission:mobile.nav.notifications')->group(function () {
        Route::get('/alerts', [MobileAlertController::class, 'index']);
        Route::get('/alerts/unread', [MobileAlertController::class, 'unread']);
        Route::post('/alerts/read', [MobileAlertController::class, 'markRead']);

        Route::get('/notification-preferences', [MobileNotificationPreferenceController::class, 'show']);
        Route::post('/notification-preferences', [MobileNotificationPreferenceController::class, 'update']);
    });

    Route::middleware('permission:mobile.nav.settings')->group(function () {
        Route::get('/tracking-settings', [MobileTrackingSettingsController::class, 'show']);
        Route::post('/tracking-settings', [MobileTrackingSettingsController::class, 'update']);
    });

    Route::get('/address', [MobileMapController::class, 'address'])
        ->middleware('permission:mobile.map.open');

    Route::middleware('permission:mobile.nav.reports')->group(function () {
        Route::get('/export/csv', [MobileExportController::class, 'csv']);
        Route::get('/export/gpx', [MobileExportController::class, 'gpx']);

        Route::get('/reports/generate', [MobileReportController::class, 'generate']);
        Route::get('/reports/export', [MobileReportController::class, 'export']);
    });
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
