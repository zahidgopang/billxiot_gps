<?php

namespace App\Services\Mobile;

/**
 * Canonical map rendering constants for web (FleetMapRenderer) and mobile native maps.
 */
class MapRenderingSpec
{
    public const VEHICLE_BODY_PX = 76;

    public const DISPLAY_SCALE = 1.2;

    public const MAX_ICON_WIDTH = 200;

    public const PULSE_SIZE_PX = 100;

    public const ROUTE_START_MARKER_PX = 48;

    public const ROUTE_END_MARKER_PX = 48;

    public const HISTORY_DOT_SCALE = 4;

    public const FOCUS_ZOOM = 16;

    public const ANIM_DURATION_MS = 1200;

    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'vehicle_body_px' => self::VEHICLE_BODY_PX,
            'display_scale' => self::DISPLAY_SCALE,
            'max_icon_width' => self::MAX_ICON_WIDTH,
            'pulse_size_px' => self::PULSE_SIZE_PX,
            'route_start_marker_px' => self::ROUTE_START_MARKER_PX,
            'route_end_marker_px' => self::ROUTE_END_MARKER_PX,
            'history_dot_scale' => self::HISTORY_DOT_SCALE,
            'focus_zoom' => self::FOCUS_ZOOM,
            'anim_duration_ms' => self::ANIM_DURATION_MS,
            'state_colors' => VehicleStatusSpec::STATE_COLORS,
            'connectivity' => [
                'delayed_min_seconds' => VehicleStatusSpec::DELAYED_MIN_SECONDS,
                'motion_window_seconds' => VehicleStatusSpec::MOTION_WINDOW_SECONDS,
                'stale_min_seconds' => VehicleStatusSpec::STALE_MIN_SECONDS,
                'offline_seconds' => VehicleStatusSpec::OFFLINE_SECONDS,
                'moving_speed_kmh' => VehicleStatusSpec::MOVING_SPEED_KMH,
                /** @deprecated use delayed_min_seconds */
                'recent_seconds' => VehicleStatusSpec::DELAYED_MIN_SECONDS,
            ],
            'anchor' => [
                'mode' => 'vehicle_center',
                'description' => 'Marker anchor at vehicle body center (GPS lat/lng). Pulse centered on same point.',
            ],
        ];
    }
}
