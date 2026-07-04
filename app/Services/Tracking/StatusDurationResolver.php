<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Mobile\VehicleStatusSpec;
use Carbon\Carbon;

class StatusDurationResolver
{
    public function __construct(
        private PositionReaderInterface $positions,
        private MobileMapStatusResolver $mapStatus,
    ) {}

    /**
     * How long the vehicle has been in the displayed status.
     *
     * - Live connectivity: duration of current motion state (running/stopped/parked/moving).
     * - Delayed/stale/offline: time since the last GPS fix (connectivity age).
     *
     * @param  array{connectivity_tier?: string}|null  $mapStatus
     * @return array{since: ?Carbon, seconds: ?int}
     */
    public function resolve(Device $device, ?DeviceLocation $latest, ?array $mapStatus = null): array
    {
        if (! $latest?->recorded_at) {
            return ['since' => null, 'seconds' => null];
        }

        $mapStatus ??= $this->mapStatus->resolve($latest, $device);
        $tier = (string) ($mapStatus['connectivity_tier'] ?? 'offline');

        if ($tier !== 'live') {
            $seconds = max(0, (int) $latest->recorded_at->diffInSeconds(now()));

            return [
                'since' => $latest->recorded_at->copy(),
                'seconds' => $seconds,
            ];
        }

        $target = VehicleStatusSpec::motionKey(
            (float) ($latest->speed ?? 0),
            (bool) $latest->ignition,
        );

        $since = $latest->recorded_at->copy();
        $from = $latest->recorded_at->copy()->subHours(12);

        $history = $this->positions->historyForDevice($device, $from, null, 'desc');

        foreach ($history as $position) {
            if (! $position->recorded_at) {
                continue;
            }

            $motion = VehicleStatusSpec::motionKey(
                (float) ($position->speed ?? 0),
                (bool) $position->ignition,
            );

            if ($motion !== $target) {
                break;
            }

            $since = $position->recorded_at->copy();
        }

        return [
            'since' => $since,
            'seconds' => max(0, (int) $since->diffInSeconds(now())),
        ];
    }
}
