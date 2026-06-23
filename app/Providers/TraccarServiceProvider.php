<?php

namespace App\Providers;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Contracts\Tracking\EventReaderInterface;
use App\Contracts\Tracking\EventWriterInterface;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Contracts\Tracking\PositionWriterInterface;
use App\Models\Device;
use App\Models\Geofence;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Observers\DeviceObserver;
use App\Observers\GeofenceObserver;
use App\Observers\UserObserver;
use App\Observers\VehicleEventObserver;
use App\Repositories\Geofences\DelegatingGeofenceStore;
use App\Repositories\Geofences\LegacyGeofenceStore;
use App\Repositories\Geofences\TraccarGeofenceStore;
use App\Repositories\Tracking\CompositeEventWriter;
use App\Repositories\Tracking\CompositePositionWriter;
use App\Repositories\Tracking\DelegatingEventReader;
use App\Repositories\Tracking\DelegatingPositionReader;
use App\Repositories\Tracking\LegacyEventReader;
use App\Repositories\Tracking\LegacyEventWriter;
use App\Repositories\Tracking\LegacyPositionReader;
use App\Repositories\Tracking\LegacyPositionWriter;
use App\Repositories\Tracking\TraccarEventMapper;
use App\Repositories\Tracking\TraccarEventReader;
use App\Repositories\Tracking\TraccarEventWriter;
use App\Repositories\Tracking\TraccarPositionReader;
use App\Repositories\Tracking\TraccarPositionWriter;
use App\Services\Traccar\TraccarDeviceAccessService;
use App\Services\Traccar\TraccarTrackingGate;
use App\Services\Traccar\TraccarUserAccessService;
use App\Services\Traccar\TraccarUserDeviceLinker;
use App\Services\Traccar\TraccarUserPasswordSync;
use App\Support\Traccar\TraccarMode;
use Illuminate\Support\ServiceProvider;

class TraccarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LegacyPositionWriter::class);
        $this->app->singleton(TraccarPositionWriter::class);
        $this->app->singleton(LegacyPositionReader::class);
        $this->app->singleton(TraccarPositionReader::class);

        $this->app->singleton(LegacyEventWriter::class);
        $this->app->singleton(TraccarEventWriter::class);
        $this->app->singleton(TraccarEventMapper::class);
        $this->app->singleton(LegacyEventReader::class);
        $this->app->singleton(TraccarEventReader::class);

        $this->app->singleton(LegacyGeofenceStore::class);
        $this->app->singleton(TraccarGeofenceStore::class);

        $this->app->singleton(PositionWriterInterface::class, CompositePositionWriter::class);
        $this->app->singleton(PositionReaderInterface::class, DelegatingPositionReader::class);
        $this->app->singleton(EventWriterInterface::class, CompositeEventWriter::class);
        $this->app->singleton(EventReaderInterface::class, DelegatingEventReader::class);
        $this->app->singleton(GeofenceStoreInterface::class, DelegatingGeofenceStore::class);
        $this->app->singleton(TraccarDeviceAccessService::class);
        $this->app->singleton(TraccarUserAccessService::class);
        $this->app->singleton(TraccarUserDeviceLinker::class);
        $this->app->singleton(TraccarUserPasswordSync::class);
        $this->app->singleton(TraccarTrackingGate::class);
    }

    public function boot(): void
    {
        $this->applyConfigDefaults();

        if (TraccarMode::isActive() && config('traccar.sync_devices_on_change', true)) {
            Device::observe(DeviceObserver::class);
        }

        if (TraccarMode::isActive() && config('traccar.sync_users_on_change', true)) {
            User::observe(UserObserver::class);
        }

        if (TraccarMode::shouldSyncObservers() && ! TraccarMode::isSingleSource()) {
            Geofence::observe(GeofenceObserver::class);
        }
    }

    private function applyConfigDefaults(): void
    {
        if (config('traccar.sync_on_change') === null) {
            config(['traccar.sync_on_change' => ! TraccarMode::isSingleSource()]);
        }

        if (config('traccar.write_legacy_tables') === null) {
            config(['traccar.write_legacy_tables' => ! TraccarMode::isSingleSource()]);
        }
    }
}
