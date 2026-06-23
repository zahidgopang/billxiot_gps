<?php

namespace App\Services\Mobile;

/**
 * Canonical vehicle status engine — shared by mobile API, web maps, and fleet UI.
 *
 * Connectivity tiers (from last GPS fix timestamp only):
 * - live:    &lt; 2 min  → Running / Stopped / Parked / Moving from telemetry
 * - delayed: 2–10 min  → Delayed (last-known motion preserved)
 * - stale:   10–30 min → Weak Signal / Stale
 * - offline: &gt; 30 min → Offline (never from ignition OFF or speed 0 alone)
 */
class VehicleStatusSpec
{
    public const MOVING_SPEED_KMH = 5;

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
        'parked' => '#94a3b8',
        'moving' => '#a855f7',
        'delayed' => '#eab308',
        'stale' => '#f59e0b',
        'offline' => '#ef4444',
        'alert' => '#ef4444',
        'blocked' => '#ef4444',
        // Legacy keys (maps / older clients)
        'idle' => '#f97316',
        'ignition_off' => '#94a3b8',
    ];

    public static function motionKey(float $speed, bool $ignition): string
    {
        if ($ignition) {
            return $speed > self::MOVING_SPEED_KMH ? 'running' : 'stopped';
        }

        return $speed > self::MOVING_SPEED_KMH ? 'moving' : 'parked';
    }

    public static function motionLabel(string $key): string
    {
        return match (self::normalizeKey($key)) {
            'running' => (string) __('app.map.status_running'),
            'stopped' => (string) __('app.map.status_stopped'),
            'parked' => (string) __('app.map.status_parked'),
            'moving' => (string) __('app.map.status_moving'),
            default => (string) __('app.map.status_stopped'),
        };
    }

    /**
     * @return 'live'|'delayed'|'stale'|'offline'
     */
    public static function connectivityTier(?int $secondsSinceUpdate): string
    {
        if ($secondsSinceUpdate === null) {
            return 'offline';
        }

        if ($secondsSinceUpdate > self::OFFLINE_SECONDS) {
            return 'offline';
        }

        if ($secondsSinceUpdate >= self::STALE_MIN_SECONDS) {
            return 'stale';
        }

        if ($secondsSinceUpdate >= self::DELAYED_MIN_SECONDS) {
            return 'delayed';
        }

        return 'live';
    }

    public static function normalizeKey(string $key): string
    {
        $k = strtolower(trim($key));

        return match ($k) {
            'idle' => 'stopped',
            'ignition_off' => 'parked',
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
    public static function resolve(?int $secondsSinceUpdate, float $speed, bool $ignition): array
    {
        $motion = [
            'key' => self::motionKey($speed, $ignition),
            'label' => self::motionLabel(self::motionKey($speed, $ignition)),
        ];

        $tier = self::connectivityTier($secondsSinceUpdate);

        if ($tier === 'offline') {
            return [
                'key' => 'offline',
                'label' => self::labelForKey('offline'),
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
