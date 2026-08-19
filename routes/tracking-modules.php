<?php

use App\Http\Controllers\GlobalTrackingController;
use App\Http\Controllers\Tracking\CommandsController;
use App\Http\Controllers\Tracking\DriversController;
use App\Http\Controllers\Tracking\MaintenanceController;
use App\Http\Controllers\Tracking\OdometerReportController;
use App\Http\Controllers\Tracking\ReportController;
use App\Http\Controllers\Tracking\TrackingEventsController;
use App\Http\Controllers\Tracking\TrackingGeofencesController;
use App\Http\Controllers\Tracking\TrackingNotificationsController;
use App\Http\Controllers\Tracking\TrackingSettingsController;
use App\Http\Controllers\Tracking\TasksController;
use Illuminate\Support\Facades\Route;

/**
 * Shared tracking module routes — register with prefix `tracking` and name `tracking.*`.
 */
return function (): void {
    Route::middleware('permission:web.map.open,web.map.live_only')->group(function (): void {
        Route::get('/', [GlobalTrackingController::class, 'index'])->name('index');
        Route::get('/live-json', [GlobalTrackingController::class, 'liveJson'])->name('live-json');
        Route::get('/device-panel', [GlobalTrackingController::class, 'devicePanel'])->name('device-panel');
        Route::get('/device-mileage', [GlobalTrackingController::class, 'deviceMileage'])->name('device-mileage');
    });

    Route::middleware('permission:web.trips.complete')->group(function (): void {
        Route::post('/complete-trip', [GlobalTrackingController::class, 'completeTrip'])->name('complete-trip');
        Route::post('/start-new-trip', [GlobalTrackingController::class, 'startNewTrip'])->name('start-new-trip');
        Route::post('/restart-trip', [GlobalTrackingController::class, 'restartTrip'])->name('restart-trip');
    });

    Route::get('/route-guidance', [GlobalTrackingController::class, 'routeGuidance'])
        ->middleware('permission:web.map.toolbar.polyline')
        ->name('route-guidance');

    Route::middleware('permission:web.history.view')->group(function (): void {
        Route::get('/history', [GlobalTrackingController::class, 'history'])->name('history');
        Route::get('/history-json', [GlobalTrackingController::class, 'historyJson'])->name('history-json');
        Route::get('/history-points-json', [GlobalTrackingController::class, 'historyPointsJson'])->name('history-points-json');
        Route::get('/history-analytics-json', [GlobalTrackingController::class, 'historyAnalyticsJson'])->name('history-analytics-json');
        Route::match(['get', 'post'], '/history-export', [GlobalTrackingController::class, 'historyExport'])->name('history-export');
        Route::get('/history-geocode', [GlobalTrackingController::class, 'historyGeocode'])->name('history-geocode');
    });

    Route::prefix('reports')->name('reports.')->middleware('permission:web.reports.view')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::match(['get', 'post'], '/generate', [ReportController::class, 'generate'])->name('generate');
        Route::match(['get', 'post'], '/export', [ReportController::class, 'export'])->name('export');
    });

    Route::get('/odometer', [OdometerReportController::class, 'index'])
        ->middleware('permission:web.reports.view')
        ->name('odometer.index');

    Route::prefix('events')->name('events.')->middleware('permission:web.events.view')->group(function () {
        Route::get('/', [TrackingEventsController::class, 'index'])->name('index');
        Route::get('/json', [TrackingEventsController::class, 'json'])->name('json');
        Route::post('/{eventId}/read', [TrackingEventsController::class, 'markRead'])->name('read');
    });

    Route::prefix('geofences')->name('geofences.')->middleware('permission:web.geofence.view')->group(function () {
        Route::get('/', [TrackingGeofencesController::class, 'index'])->name('index');
        Route::get('/json', [TrackingGeofencesController::class, 'json'])->name('json');
        Route::middleware('permission:web.geofence.manage')->group(function (): void {
            Route::post('/', [TrackingGeofencesController::class, 'store'])->name('store');
            Route::post('/{geofence}', [TrackingGeofencesController::class, 'update'])->name('update');
            Route::delete('/{geofence}', [TrackingGeofencesController::class, 'destroy'])->name('destroy');
        });
    });

    Route::prefix('notifications')->name('notifications.')->middleware('permission:web.tracking.hub.notifications')->group(function () {
        Route::get('/', [TrackingNotificationsController::class, 'index'])->name('index');
        Route::get('/json', [TrackingNotificationsController::class, 'json'])->name('json');
        Route::get('/inbox', [TrackingNotificationsController::class, 'inbox'])->name('inbox');
        Route::post('/read', [TrackingNotificationsController::class, 'markRead'])->name('read');
        Route::post('/', [TrackingNotificationsController::class, 'update'])->name('update');
    });

    Route::prefix('maintenance')->name('maintenance.')->middleware('permission:web.tracking.hub.maintenance')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');
        Route::get('/json', [MaintenanceController::class, 'json'])->name('json');
        Route::post('/', [MaintenanceController::class, 'store'])->name('store');
        Route::post('/{maintenance}/complete', [MaintenanceController::class, 'complete'])->name('complete');
        Route::post('/{maintenance}', [MaintenanceController::class, 'update'])->name('update');
        Route::delete('/{maintenance}', [MaintenanceController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('drivers')->name('drivers.')->middleware('permission:web.tracking.hub.drivers')->group(function () {
        Route::get('/', [DriversController::class, 'index'])->name('index');
        Route::get('/json', [DriversController::class, 'json'])->name('json');
        Route::post('/', [DriversController::class, 'store'])->name('store');
        Route::post('/{driver}/assign', [DriversController::class, 'assign'])->name('assign');
        Route::delete('/{driver}', [DriversController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('commands')->name('commands.')->middleware('permission:web.map.toolbar.commands')->group(function () {
        Route::get('/', [CommandsController::class, 'index'])->name('index');
        Route::get('/json', [CommandsController::class, 'json'])->name('json');
        Route::post('/send', [CommandsController::class, 'send'])->name('send');
        Route::delete('/{command}', [CommandsController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('tasks')->name('tasks.')->middleware('permission:web.tracking.hub.tasks')->group(function () {
        Route::get('/', [TasksController::class, 'index'])->name('index');
        Route::get('/json', [TasksController::class, 'json'])->name('json');
        Route::get('/export', [TasksController::class, 'export'])->name('export');
        Route::post('/', [TasksController::class, 'store'])->name('store');
        Route::delete('/all', [TasksController::class, 'destroyAll'])->name('destroy-all');
        Route::delete('/{task}', [TasksController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('settings')->name('settings.')->middleware('permission:web.settings.view')->group(function () {
        Route::get('/', [TrackingSettingsController::class, 'index'])->name('index');
        Route::get('/json', [TrackingSettingsController::class, 'json'])->name('json');
        Route::post('/', [TrackingSettingsController::class, 'update'])->name('update');
    });
};
