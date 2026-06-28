<?php

use App\Http\Controllers\GlobalTrackingController;
use App\Http\Controllers\Tracking\CommandsController;
use App\Http\Controllers\Tracking\DriversController;
use App\Http\Controllers\Tracking\MaintenanceController;
use App\Http\Controllers\Tracking\ReportController;
use App\Http\Controllers\Tracking\TrackingEventsController;
use App\Http\Controllers\Tracking\TrackingGeofencesController;
use App\Http\Controllers\Tracking\TrackingNotificationsController;
use App\Http\Controllers\Tracking\TrackingSettingsController;
use App\Http\Controllers\Tracking\TasksController;
use Illuminate\Support\Facades\Route;

/**
 * Shared tracking module routes — register inside panel groups with name prefix admin.tracking.* etc.
 */
return function (): void {
    Route::get('/', [GlobalTrackingController::class, 'index'])->name('index');
    Route::get('/live-json', [GlobalTrackingController::class, 'liveJson'])->name('live-json');
    Route::get('/device-panel', [GlobalTrackingController::class, 'devicePanel'])->name('device-panel');
    Route::get('/device-mileage', [GlobalTrackingController::class, 'deviceMileage'])->name('device-mileage');
    Route::get('/history', [GlobalTrackingController::class, 'history'])->name('history');
    Route::get('/history-json', [GlobalTrackingController::class, 'historyJson'])->name('history-json');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/generate', [ReportController::class, 'generate'])->name('generate');
        Route::get('/export', [ReportController::class, 'export'])->name('export');
    });

    Route::prefix('events')->name('events.')->group(function () {
        Route::get('/', [TrackingEventsController::class, 'index'])->name('index');
        Route::get('/json', [TrackingEventsController::class, 'json'])->name('json');
        Route::post('/{eventId}/read', [TrackingEventsController::class, 'markRead'])->name('read');
    });

    Route::prefix('geofences')->name('geofences.')->group(function () {
        Route::get('/', [TrackingGeofencesController::class, 'index'])->name('index');
        Route::get('/json', [TrackingGeofencesController::class, 'json'])->name('json');
        Route::post('/', [TrackingGeofencesController::class, 'store'])->name('store');
        Route::post('/{geofence}', [TrackingGeofencesController::class, 'update'])->name('update');
        Route::delete('/{geofence}', [TrackingGeofencesController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [TrackingNotificationsController::class, 'index'])->name('index');
        Route::get('/json', [TrackingNotificationsController::class, 'json'])->name('json');
        Route::post('/', [TrackingNotificationsController::class, 'update'])->name('update');
    });

    Route::prefix('maintenance')->name('maintenance.')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');
        Route::get('/json', [MaintenanceController::class, 'json'])->name('json');
        Route::post('/', [MaintenanceController::class, 'store'])->name('store');
        Route::post('/{maintenance}', [MaintenanceController::class, 'update'])->name('update');
        Route::delete('/{maintenance}', [MaintenanceController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('drivers')->name('drivers.')->group(function () {
        Route::get('/', [DriversController::class, 'index'])->name('index');
        Route::get('/json', [DriversController::class, 'json'])->name('json');
        Route::post('/', [DriversController::class, 'store'])->name('store');
        Route::post('/{driver}/assign', [DriversController::class, 'assign'])->name('assign');
        Route::delete('/{driver}', [DriversController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('commands')->name('commands.')->group(function () {
        Route::get('/', [CommandsController::class, 'index'])->name('index');
        Route::get('/json', [CommandsController::class, 'json'])->name('json');
        Route::post('/send', [CommandsController::class, 'send'])->name('send');
    });

    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [TasksController::class, 'index'])->name('index');
        Route::get('/json', [TasksController::class, 'json'])->name('json');
        Route::get('/export', [TasksController::class, 'export'])->name('export');
        Route::post('/', [TasksController::class, 'store'])->name('store');
        Route::delete('/all', [TasksController::class, 'destroyAll'])->name('destroy-all');
        Route::delete('/{task}', [TasksController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [TrackingSettingsController::class, 'index'])->name('index');
        Route::get('/json', [TrackingSettingsController::class, 'json'])->name('json');
        Route::post('/', [TrackingSettingsController::class, 'update'])->name('update');
    });
};
