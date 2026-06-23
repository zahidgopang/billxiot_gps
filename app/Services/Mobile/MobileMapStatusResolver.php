<?php

namespace App\Services\Mobile;

use App\Models\Device;
use App\Models\DeviceLocation;

/**
 * Canonical vehicle status for mobile API, web live map, and fleet lists.
 *
 * @see VehicleStatusSpec for thresholds and motion rules.
 */
class MobileMapStatusResolver
{
    public const MOVING_SPEED_KMH = VehicleStatusSpec::MOVING_SPEED_KMH;

    public const DELAYED_MIN_SECONDS = VehicleStatusSpec::DELAYED_MIN_SECONDS;

    public const STALE_MIN_SECONDS = VehicleStatusSpec::STALE_MIN_SECONDS;

    public const OFFLINE_SECONDS = VehicleStatusSpec::OFFLINE_SECONDS;

    /** @deprecated use DELAYED_MIN_SECONDS */
    public const RECENT_SECONDS = VehicleStatusSpec::RECENT_SECONDS;

    /**
     * @return array{
     *     label: string,
     *     key: string,
     *     connectivity_tier: string,
     *     last_known_status_key: ?string,
     *     last_known_status: ?string,
     *     last_known_speed: ?float,
     *     last_known_ignition: ?bool
     * }
     */
    public function resolve(?DeviceLocation $latest, Device $device): array
    {
        $lastKnown = $this->motionStatus($latest);

        if ($device->status === 'blocked') {
            return $this->pack(
                key: 'blocked',
                label: (string) __('app.common.blocked'),
                tier: 'offline',
                lastKnown: $lastKnown,
                latest: $latest,
            );
        }

        if ($device->status === 'inactive') {
            return $this->pack(
                key: 'offline',
                label: (string) __('app.common.inactive'),
                tier: 'offline',
                lastKnown: $lastKnown,
                latest: $latest,
            );
        }

        if (! $latest || ! $latest->recorded_at) {
            return $this->pack(
                key: 'offline',
                label: (string) __('app.map.status_offline'),
                tier: 'offline',
                lastKnown: $lastKnown,
                latest: $latest,
            );
        }

        $seconds = $this->secondsSinceUpdate($latest);
        $speed = (float) ($latest->speed ?? 0);
        $ignition = (bool) $latest->ignition;

        if ($latest->power_cut && VehicleStatusSpec::connectivityTier($seconds) === 'live') {
            return $this->pack(
                key: 'alert',
                label: (string) __('app.map.status_power_cut'),
                tier: 'live',
                lastKnown: $lastKnown,
                latest: $latest,
            );
        }

        if ($latest->panic && VehicleStatusSpec::connectivityTier($seconds) === 'live') {
            return $this->pack(
                key: 'alert',
                label: (string) __('app.map.status_sos'),
                tier: 'live',
                lastKnown: $lastKnown,
                latest: $latest,
            );
        }

        $resolved = VehicleStatusSpec::resolve($seconds, $speed, $ignition);

        return $this->pack(
            key: $resolved['key'],
            label: $resolved['label'],
            tier: $resolved['tier'],
            lastKnown: $lastKnown,
            latest: $latest,
        );
    }

    /**
     * Motion status from the last GPS fix (ignores connectivity age).
     *
     * @return array{key: string, label: string}|null
     */
    public function motionStatus(?DeviceLocation $latest): ?array
    {
        if (! $latest) {
            return null;
        }

        $key = VehicleStatusSpec::motionKey(
            (float) ($latest->speed ?? 0),
            (bool) $latest->ignition,
        );

        return [
            'key' => $key,
            'label' => VehicleStatusSpec::motionLabel($key),
        ];
    }

    public function isRecentlyOnline(?DeviceLocation $latest): bool
    {
        if (! $latest || ! $latest->recorded_at) {
            return false;
        }

        $seconds = $this->secondsSinceUpdate($latest);

        return $seconds !== null
            && VehicleStatusSpec::connectivityTier($seconds) !== 'offline';
    }

    public function secondsSinceUpdate(?DeviceLocation $latest): ?int
    {
        if (! $latest?->recorded_at) {
            return null;
        }

        return (int) $latest->recorded_at->diffInSeconds(now());
    }

    /** @deprecated use secondsSinceUpdate() */
    public function minutesSinceUpdate(?DeviceLocation $latest): ?int
    {
        $seconds = $this->secondsSinceUpdate($latest);

        return $seconds === null ? null : (int) floor($seconds / 60);
    }

    public function hasValidGpsFix(?DeviceLocation $latest): bool
    {
        if (! $latest) {
            return false;
        }

        $fix = strtolower(trim((string) ($latest->gps_fix ?? '')));
        if (in_array($fix, ['0', 'false', 'invalid', 'no', 'no fix', 'no_fix'], true)) {
            return false;
        }

        $lat = (float) ($latest->lat ?? 0);
        $lng = (float) ($latest->lng ?? 0);

        if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
            return false;
        }

        if (in_array($fix, ['fix', '2d', '3d'], true)) {
            return true;
        }

        if ((int) ($latest->satellites ?? 0) >= 3) {
            return true;
        }

        if ((int) ($latest->gps_signal ?? 0) > 0) {
            return true;
        }

        return true;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Device>  $devices
     * @return array{running: int, parked: int, idle: int, stopped: int, delayed: int, stale: int, offline: int, alert: int, with_gps: int}
     */
    public function fleetCounts(\Illuminate\Support\Collection $devices): array
    {
        $counts = [
            'running' => 0,
            'parked' => 0,
            'idle' => 0,
            'stopped' => 0,
            'delayed' => 0,
            'stale' => 0,
            'offline' => 0,
            'alert' => 0,
            'with_gps' => 0,
        ];

        foreach ($devices as $device) {
            $latest = $device->latestLocation;
            $map = $this->resolve($latest, $device);

            if ($latest) {
                $counts['with_gps']++;
            }

            match (VehicleStatusSpec::normalizeKey($map['key'])) {
                'running' => $counts['running']++,
                'stopped' => $counts['stopped']++,
                'parked' => $counts['parked']++,
                'moving' => $counts['running']++,
                'delayed' => $counts['delayed']++,
                'stale' => $counts['delayed']++,
                'alert' => $counts['alert']++,
                'idle' => $counts['stopped']++,
                default => $counts['offline']++,
            };
        }

        return $counts;
    }

    /**
     * @param  array{key: string, label: string}|null  $lastKnown
     * @return array{
     *     label: string,
     *     key: string,
     *     connectivity_tier: string,
     *     last_known_status_key: ?string,
     *     last_known_status: ?string,
     *     last_known_speed: ?float,
     *     last_known_ignition: ?bool
     * }
     */
    private function pack(
        string $key,
        string $label,
        string $tier,
        ?array $lastKnown,
        ?DeviceLocation $latest,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'connectivity_tier' => $tier,
            'last_known_status_key' => $lastKnown['key'] ?? null,
            'last_known_status' => $lastKnown['label'] ?? null,
            'last_known_speed' => $latest !== null ? (float) ($latest->speed ?? 0) : null,
            'last_known_ignition' => $latest !== null ? (bool) $latest->ignition : null,
        ];
    }
}
