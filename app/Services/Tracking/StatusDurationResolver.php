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
        $historyHours = (int) config('tracking.status_duration_history_hours', 2);
        $from = $latest->recorded_at->copy()->subHours(max(1, $historyHours));

        $cacheKey = "device.{$device->id}.status_duration.{$target}";
        $cached = cache()->get($cacheKey);
        if (is_array($cached) && isset($cached['since'], $cached['seconds'])) {
            return [
                'since' => Carbon::parse($cached['since']),
                'seconds' => (int) $cached['seconds'],
            ];
        }

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

        $result = [
            'since' => $since,
            'seconds' => max(0, (int) $since->diffInSeconds(now())),
        ];

        cache()->put($cacheKey, [
            'since' => $since->toIso8601String(),
            'seconds' => $result['seconds'],
        ], now()->addSeconds((int) config('tracking.status_duration_cache_seconds', 60)));

        return $result;
    }
}
