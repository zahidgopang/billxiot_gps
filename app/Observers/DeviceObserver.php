<?php

namespace App\Observers;

use App\Models\Device;
use App\Services\Traccar\TraccarEntityProvisioner;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;

class DeviceObserver
{
    public function __construct(
        private TraccarEntityProvisioner $provisioner,
    ) {}

    public function created(Device $device): void
    {
        $this->syncIfEnabled($device);
    }

    public function updated(Device $device): void
    {
        $this->syncIfEnabled($device);
    }

    public function deleted(Device $device): void
    {
        if (! TraccarMode::isActive() || ! TraccarSchema::isReady()) {
            return;
        }

        try {
            $this->provisioner->unlinkDevice($device);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function syncIfEnabled(Device $device): void
    {
        if (! TraccarMode::isActive() || ! TraccarSchema::isReady()) {
            return;
        }

        try {
            $this->provisioner->provisionDevice($device);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
