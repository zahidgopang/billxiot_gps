<?php

namespace App\Services\Mobile;

/**
 * Canonical vehicle status engine — shared by mobile API, web maps, and fleet UI.
 *
 * Connectivity tiers (from last GPS fix timestamp only):
 * - live:    &lt; 2 min  → Running / Stopped / Parked / Moving from telemetry
 * - delayed: 2–10 min  → Delayed (last-known motion preserved)
 * - stale:   10–30 min → Weak Signal / Stale
 * - offline: &gt; 30 min → Stopped if a last GPS fix exists; Offline only with no position
 */
class VehicleStatusSpec
{
    public const MOVING_SPEED_KMH = 1;

    /** Fresh motion classification requires fix newer than this. */
    public const DELAYED_MIN_SECONDS = 120;

    /** Upper bound for delayed tier; motion window for Running/Stopped/Parked/Moving. */
    public const MOTION_WINDOW_SECONDS = 600;

    public const STALE_MIN_SECONDS = 600;

    public const OFFLINE_SECONDS = 1800;

    /** @deprecated use DELAYED_MIN_SECONDS */
    public const RECENT_SECONDS = self::DELAYED_MIN_SECONDS;

    /** @var array<string, string> */
    public const STATE_COLORS = [
        'running' => '#22c55e',
        'stopped' => '#f97316',
        'idle' => '#f97316',
        'parked' => '#94a3b8',
        'parking' => '#94a3b8',
        'moving' => '#a855f7',
        'delayed' => '#eab308',
        'stale' => '#f59e0b',
        'offline' => '#ef4444',
        'alert' => '#ef4444',
        'blocked' => '#ef4444',
        // Legacy keys (maps / older clients)
        'ignition_off' => '#94a3b8',
    ];

    public static function motionKey(float $speed, bool $ignition): string
    {
        if ($ignition) {
            return $speed > self::MOVING_SPEED_KMH ? 'running' : 'idle';
        }

        return $speed > self::MOVING_SPEED_KMH ? 'moving' : 'parked';
    }

    public static function tripStatusKey(float $speed, bool $ignition): string
    {
        if ($speed > self::MOVING_SPEED_KMH) {
            return 'moving';
        }

        return $ignition ? 'idle' : 'parking';
    }

    public static function motionLabel(string $key): string
    {
        return match (self::normalizeKey($key)) {
            'running' => (string) __('app.map.status_running'),
            'stopped' => (string) __('app.map.status_stopped'),
            'idle' => (string) __('app.map.status_idle'),
            'parked' => (string) __('app.map.status_parked'),
            'parking' => (string) __('app.map.status_parked'),
            'moving' => (string) __('app.map.status_moving'),
            default => (string) __('app.map.status_stopped'),
        };
    }

    public static function connectivityTier(?int $secondsSinceUpdate, ?array $thresholds = null): string
    {
        if ($secondsSinceUpdate === null) {
            return 'offline';
        }

        $delayed = (int) ($thresholds['delayed_min_seconds'] ?? self::DELAYED_MIN_SECONDS);
        $stale = (int) ($thresholds['stale_min_seconds'] ?? self::STALE_MIN_SECONDS);
        $offline = (int) ($thresholds['offline_seconds'] ?? self::OFFLINE_SECONDS);

        if ($secondsSinceUpdate > $offline) {
            return 'offline';
        }

        if ($secondsSinceUpdate >= $stale) {
            return 'stale';
        }

        if ($secondsSinceUpdate >= $delayed) {
            return 'delayed';
        }

        return 'live';
    }

    public static function normalizeKey(string $key): string
    {
        $k = strtolower(trim($key));

        return match ($k) {
            'ignition_off' => 'parked',
            'parking' => 'parked',
            default => $k,
        };
    }

    public static function colorForKey(string $key): string
    {
        $k = self::normalizeKey($key);

        return self::STATE_COLORS[$k] ?? self::STATE_COLORS['stopped'];
    }

    public static function labelForKey(string $key): string
    {
        return match (self::normalizeKey($key)) {
            'running' => (string) __('app.map.status_running'),
            'stopped' => (string) __('app.map.status_stopped'),
            'idle' => (string) __('app.map.status_idle'),
            'parked' => (string) __('app.map.status_parked'),
            'moving' => (string) __('app.map.status_moving'),
            'delayed' => (string) __('app.map.status_delayed'),
            'stale' => (string) __('app.map.status_stale'),
            'offline' => (string) __('app.map.status_offline'),
            'alert' => (string) __('app.map.status_sos'),
            'blocked' => (string) __('app.common.blocked'),
            default => (string) __('app.map.status_stopped'),
        };
    }

    /**
     * @return array{key: string, label: string, tier: string}
     */
    public static function resolve(?int $secondsSinceUpdate, float $speed, bool $ignition, ?array $thresholds = null): array
    {
        $movingSpeed = (float) ($thresholds['moving_speed_kmh'] ?? self::MOVING_SPEED_KMH);
        $motionKey = $ignition
            ? ($speed > $movingSpeed ? 'running' : 'idle')
            : ($speed > $movingSpeed ? 'moving' : 'parked');
        $motion = [
            'key' => $motionKey,
            'label' => self::motionLabel($motionKey),
        ];

        $tier = self::connectivityTier($secondsSinceUpdate, $thresholds);

        if ($tier === 'offline') {
            // Had GPS that aged out (typical parked/stopped vehicle): show Stopped.
            // No-fix / inactive / blocked stay Offline via the resolver, not this path.
            return [
                'key' => 'stopped',
                'label' => self::labelForKey('stopped'),
                'tier' => 'offline',
                'motion' => $motion,
            ];
        }

        if ($tier === 'stale') {
            return [
                'key' => 'stale',
                'label' => self::labelForKey('stale'),
                'tier' => 'stale',
                'motion' => $motion,
            ];
        }

        if ($tier === 'delayed') {
            return [
                'key' => 'delayed',
                'label' => self::labelForKey('delayed'),
                'tier' => 'delayed',
                'motion' => $motion,
            ];
        }

        return [
            'key' => $motion['key'],
            'label' => $motion['label'],
            'tier' => 'live',
            'motion' => $motion,
        ];
    }
}
