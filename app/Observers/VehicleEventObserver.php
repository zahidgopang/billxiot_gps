<?php

namespace App\Observers;

use App\Models\VehicleEvent;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;

class VehicleEventObserver
{
    public function __construct(
        private TraccarSyncService $sync,
    ) {}

    public function created(VehicleEvent $event): void
    {
        if (! TraccarMode::isActive() || ! TraccarSchema::hasEvents()) {
            return;
        }

        try {
            $this->sync->syncVehicleEvent($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
