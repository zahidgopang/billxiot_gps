<?php

namespace App\Services\Traccar;

use App\Models\Device;
use App\Models\Geofence;
use App\Models\User;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;

/**
 * Writes Laravel business entities into Traccar tc_* tables (single source of truth).
 * Never deletes tc_user_device from geofence operations.
 */
class TraccarEntityProvisioner
{
    public function __construct(
        private TraccarSyncService $sync,
    ) {}

    public function provisionUser(User $user, ?string $plainPassword = null): void
    {
        if (! $this->shouldWrite()) {
            return;
        }

        try {
            $this->sync->syncUser($user, $plainPassword);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function provisionDevice(Device $device): void
    {
        if (! $this->shouldWrite()) {
            return;
        }

        try {
            $this->sync->syncDevice($device);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function provisionGeofence(Geofence $geofence): void
    {
        if (! $this->shouldWrite()) {
            return;
        }

        try {
            $this->sync->syncGeofence($geofence, true);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function removeGeofence(Geofence $geofence): void
    {
        if (! $this->shouldWrite()) {
            return;
        }

        try {
            $this->sync->removeGeofence($geofence);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function unlinkDevice(Device $device): void
    {
        if (! $this->shouldWrite()) {
            return;
        }

        try {
            $this->sync->unlinkDevice($device);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function unlinkUser(User $user): void
    {
        if (! $this->shouldWrite()) {
            return;
        }

        try {
            $this->sync->unlinkUser($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function shouldWrite(): bool
    {
        return TraccarMode::isActive() && TraccarSchema::isReady();
    }
}
