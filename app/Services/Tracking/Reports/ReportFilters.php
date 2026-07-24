<?php

namespace App\Services\Tracking\Reports;

use App\Services\Tracking\HistoryAnalyticsService;
use Illuminate\Http\Request;

/**
 * Optional Wialon-style filters for tracking report generation.
 *
 * Defaults preserve legacy behaviour when omitted from the request
 * (except the web UI may send stop_min_seconds=60 for the new “> 1 min” default).
 */
final class ReportFilters
{
    public const STOP_PRESETS_MINUTES = [1, 5, 10, 15, 30, 60];

    public function __construct(
        public readonly bool $ignoreEmpty = false,
        public readonly bool $showCoordinates = true,
        public readonly bool $showAddresses = false,
        public readonly bool $markersInsteadOfAddresses = false,
        public readonly bool $zonesInsteadOfAddresses = false,
        public readonly int $stopMinSeconds = HistoryAnalyticsService::STOP_MIN_SECONDS,
        public readonly ?float $speedLimitKmh = null,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    public static function fromRequest(Request $request): self
    {
        $ignoreEmpty = self::boolFrom($request, 'ignore_empty', false);
        $showCoordinates = self::boolFrom($request, 'show_coordinates', true);
        $showAddresses = self::boolFrom($request, 'show_addresses', false);
        $markers = self::boolFrom($request, 'markers_instead_of_addresses', false);
        $zones = self::boolFrom($request, 'zones_instead_of_addresses', false);

        $stopMinSeconds = HistoryAnalyticsService::STOP_MIN_SECONDS;
        if ($request->filled('stop_min_seconds')) {
            $stopMinSeconds = max(1, (int) $request->input('stop_min_seconds', $request->query('stop_min_seconds')));
        } elseif ($request->filled('stop_min_minutes')) {
            $stopMinSeconds = max(1, (int) round(((float) $request->input('stop_min_minutes', $request->query('stop_min_minutes'))) * 60));
        }

        $speedLimit = null;
        $rawSpeed = $request->input('speed_limit_kmh', $request->query('speed_limit_kmh'));
        if ($rawSpeed !== null && $rawSpeed !== '') {
            $parsed = (float) $rawSpeed;
            if ($parsed > 0) {
                $speedLimit = $parsed;
            }
        }

        return new self(
            ignoreEmpty: $ignoreEmpty,
            showCoordinates: $showCoordinates,
            showAddresses: $showAddresses,
            markersInsteadOfAddresses: $markers,
            zonesInsteadOfAddresses: $zones,
            stopMinSeconds: $stopMinSeconds,
            speedLimitKmh: $speedLimit,
        );
    }

    /**
     * @return array{
     *   ignore_empty: bool,
     *   show_coordinates: bool,
     *   show_addresses: bool,
     *   markers_instead_of_addresses: bool,
     *   zones_instead_of_addresses: bool,
     *   stop_min_seconds: int,
     *   speed_limit_kmh: float|null
     * }
     */
    public function toArray(): array
    {
        return [
            'ignore_empty' => $this->ignoreEmpty,
            'show_coordinates' => $this->showCoordinates,
            'show_addresses' => $this->showAddresses,
            'markers_instead_of_addresses' => $this->markersInsteadOfAddresses,
            'zones_instead_of_addresses' => $this->zonesInsteadOfAddresses,
            'stop_min_seconds' => $this->stopMinSeconds,
            'speed_limit_kmh' => $this->speedLimitKmh,
        ];
    }

    public function needsLocationLabels(): bool
    {
        return $this->showAddresses
            || $this->markersInsteadOfAddresses
            || $this->zonesInsteadOfAddresses;
    }

    /**
     * Which filter controls apply to a report type (for UI visibility).
     *
     * Core Wialon-style filters stay visible for all report types so the sidebar
     * always matches the expected options. Speed limit remains focused on
     * overspeed-related reports.
     *
     * @return list<string>
     */
    public static function applicableKeys(string $type): array
    {
        $keys = [
            'ignore_empty',
            'show_coordinates',
            'show_addresses',
            'markers_instead_of_addresses',
            'zones_instead_of_addresses',
            'stops',
        ];

        if (in_array($type, ['overspeeds', 'summary'], true)) {
            $keys[] = 'speed_limit';
        }

        return $keys;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function applicabilityMap(): array
    {
        $types = [
            'summary', 'object_info', 'current_position', 'trips', 'trips_stops', 'stops',
            'mileage', 'odometer', 'overspeeds', 'zone_inout', 'events', 'diesel',
            'fuel_fillings', 'service', 'tasks', 'speed', 'altitude', 'ignition',
            'route', 'positions',
        ];

        $map = [];
        foreach ($types as $type) {
            $map[$type] = self::applicableKeys($type);
        }

        return $map;
    }

    private static function boolFrom(Request $request, string $key, bool $default): bool
    {
        if (! $request->exists($key) && ! $request->query->has($key)) {
            return $default;
        }

        $value = $request->input($key, $request->query($key));

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
