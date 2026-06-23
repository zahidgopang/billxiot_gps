<?php

namespace App\Observers;

use App\Models\Geofence;
use App\Services\Traccar\TraccarEntityProvisioner;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;

class GeofenceObserver
{
    public function __construct(
        private TraccarEntityProvisioner $provisioner,
    ) {}

    public function created(Geofence $geofence): void
    {
        $this->syncIfEnabled($geofence);
    }

    public function updated(Geofence $geofence): void
    {
        if (TraccarMode::isActive() && TraccarSchema::hasGeofences() && $geofence->wasChanged('device_id')) {
            $oldDeviceId = $geofence->getOriginal('device_id');

            if ($oldDeviceId) {
                try {
                    app(TraccarSyncService::class)->detachGeofenceFromLaravelDevice($geofence, (int) $oldDeviceId);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $this->syncIfEnabled($geofence);
    }

    public function deleted(Geofence $geofence): void
    {
        if (! TraccarMode::isActive() || ! TraccarSchema::hasGeofences()) {
            return;
        }

        try {
            $this->provisioner->removeGeofence($geofence);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function syncIfEnabled(Geofence $geofence): void
    {
        if (! TraccarMode::isActive() || ! TraccarSchema::hasGeofences()) {
            return;
        }

        try {
            $this->provisioner->provisionGeofence($geofence);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
