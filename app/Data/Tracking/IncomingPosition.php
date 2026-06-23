<?php

namespace App\Data\Tracking;

use App\Models\Device;
use Carbon\Carbon;

final class IncomingPosition
{
    public function __construct(
        public Device $device,
        public float $lat,
        public float $lng,
        public float $speedKmh,
        public float $heading,
        public ?int $battery,
        public Carbon $recordedAt,
        public bool $ignition = false,
        public bool $acc = false,
        public mixed $gsmSignal = null,
        public mixed $gpsSignal = null,
        public mixed $satellites = null,
        public mixed $odometer = null,
        public bool $powerCut = false,
        public bool $panic = false,
        public ?string $gpsFix = null,
    ) {}

    public function toLegacyAttributes(): array
    {
        return [
            'device_id' => $this->device->id,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'speed' => $this->speedKmh,
            'heading' => $this->heading,
            'battery_level' => $this->battery,
            'recorded_at' => $this->recordedAt,
            'ignition' => $this->ignition,
            'acc' => $this->acc,
            'gsm_signal' => $this->gsmSignal,
            'gps_signal' => $this->gpsSignal,
            'satellites' => $this->satellites,
            'odometer' => $this->odometer,
            'power_cut' => $this->powerCut,
            'panic' => $this->panic,
            'gps_fix' => $this->gpsFix,
        ];
    }
}
