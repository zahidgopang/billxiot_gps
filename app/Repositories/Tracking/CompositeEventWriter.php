<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\EventWriterInterface;
use App\Models\Device;
use App\Models\VehicleEvent;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;

class CompositeEventWriter implements EventWriterInterface
{
    public function __construct(
        private LegacyEventWriter $legacy,
        private TraccarEventWriter $traccar,
    ) {}

    public function record(
        Device $device,
        string $type,
        string $title,
        string $message,
        ?float $speed,
        float $lat,
        float $lng,
        Carbon $at,
        ?int $geofenceId = null,
        array $meta = [],
        ?int $traccarPositionId = null
    ): VehicleEvent {
        if (TraccarMode::isSingleSource() && TraccarSchema::hasEvents()) {
            return $this->traccar->record(
                $device,
                $type,
                $title,
                $message,
                $speed,
                $lat,
                $lng,
                $at,
                $geofenceId,
                $meta,
                $traccarPositionId
            );
        }

        $event = null;

        if (TraccarMode::writesLegacy()) {
            $event = $this->legacy->record(
                $device,
                $type,
                $title,
                $message,
                $speed,
                $lat,
                $lng,
                $at,
                $geofenceId,
                $meta,
                $traccarPositionId
            );
        }

        if (TraccarMode::writesTraccar() && TraccarSchema::hasEvents()) {
            try {
                $traccarEvent = $this->traccar->record(
                    $device,
                    $type,
                    $title,
                    $message,
                    $speed,
                    $lat,
                    $lng,
                    $at,
                    $geofenceId,
                    $meta,
                    $traccarPositionId
                );

                if ($event && $traccarEvent->id) {
                    $event->setAttribute('traccar_event_id', $traccarEvent->id);
                }

                return $event ?? $traccarEvent;
            } catch (\Throwable $e) {
                report($e);

                if ($event) {
                    return $event;
                }

                throw $e;
            }
        }

        if ($event) {
            return $event;
        }

        return $this->legacy->record(
            $device,
            $type,
            $title,
            $message,
            $speed,
            $lat,
            $lng,
            $at,
            $geofenceId,
            $meta,
            $traccarPositionId
        );
    }
}
