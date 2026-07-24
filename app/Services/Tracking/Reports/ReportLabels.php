<?php

namespace App\Services\Tracking\Reports;

final class ReportLabels
{
    public static function vehicle(): string
    {
        return (string) __('app.tracking.report_col_vehicle');
    }

    public static function plate(): string
    {
        return (string) __('app.tracking.report_col_plate');
    }

    public static function driver(): string
    {
        return (string) __('app.tracking.report_col_driver');
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0' . (string) __('app.tracking.report_duration_seconds');
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        $parts = [];

        if ($h > 0) {
            $parts[] = $h . (string) __('app.tracking.report_duration_hours');
        }
        if ($h > 0 || $m > 0) {
            $parts[] = $m . (string) __('app.tracking.report_duration_minutes');
        }
        if ($h === 0) {
            $parts[] = $s . (string) __('app.tracking.report_duration_seconds');
        }

        return implode(' ', $parts);
    }

    public static function formatIgnition(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? (string) __('app.tracking.report_ignition_on')
            : (string) __('app.tracking.report_ignition_off');
    }

    public static function address(): string
    {
        return (string) __('app.tracking.report_col_address');
    }

    public static function startAddress(): string
    {
        return (string) __('app.tracking.report_col_start_address');
    }

    public static function endAddress(): string
    {
        return (string) __('app.tracking.report_col_end_address');
    }

    /**
     * Stable field catalog for Custom Reports (key + localized label).
     *
     * @return list<array{key: string, label: string}>
     */
    public static function fieldsForType(string $type): array
    {
        $f = static fn (string $key, string $label): array => ['key' => $key, 'label' => $label];

        return match ($type) {
            'trips' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('driver', self::driver()),
                $f('start', (string) __('app.tracking.report_col_start')),
                $f('end', (string) __('app.tracking.report_col_end')),
                $f('start_lat', (string) __('app.tracking.report_col_start_lat')),
                $f('start_lng', (string) __('app.tracking.report_col_start_lng')),
                $f('end_lat', (string) __('app.tracking.report_col_end_lat')),
                $f('end_lng', (string) __('app.tracking.report_col_end_lng')),
                $f('start_address', self::startAddress()),
                $f('end_address', self::endAddress()),
                $f('maps_start', (string) __('app.tracking.report_col_maps_start')),
                $f('maps_end', (string) __('app.tracking.report_col_maps_end')),
                $f('distance_km', (string) __('app.tracking.report_col_distance_km')),
                $f('duration', (string) __('app.tracking.report_col_duration')),
                $f('moving_time', (string) __('app.tracking.report_col_moving_time')),
                $f('stops', (string) __('app.tracking.report_col_stops')),
                $f('route_points', (string) __('app.tracking.report_col_route_points')),
                $f('max_speed', (string) __('app.tracking.report_col_max_speed')),
                $f('avg_speed', (string) __('app.tracking.report_col_avg_speed')),
            ],
            'stops' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('status', (string) __('app.tracking.report_col_status')),
                $f('start', (string) __('app.tracking.report_col_start')),
                $f('end', (string) __('app.tracking.report_col_end')),
                $f('duration', (string) __('app.tracking.report_col_duration')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'trips_stops' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('segment', (string) __('app.tracking.report_col_segment')),
                $f('start', (string) __('app.tracking.report_col_start')),
                $f('end', (string) __('app.tracking.report_col_end')),
                $f('duration', (string) __('app.tracking.report_col_duration')),
                $f('distance_km', (string) __('app.tracking.report_col_distance_km')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
                $f('stops', (string) __('app.tracking.report_col_stops')),
            ],
            'mileage' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('date', (string) __('app.tracking.report_col_date')),
                $f('distance_km', (string) __('app.tracking.report_col_distance_km')),
                $f('duration', (string) __('app.tracking.report_col_duration')),
                $f('points', (string) __('app.tracking.report_col_points')),
                $f('start_time', (string) __('app.tracking.report_col_start_time')),
                $f('end_time', (string) __('app.tracking.report_col_end_time')),
                $f('maps_start', (string) __('app.tracking.report_col_maps_start')),
                $f('maps_end', (string) __('app.tracking.report_col_maps_end')),
            ],
            'odometer' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('distance_km', (string) __('app.tracking.report_col_distance_km')),
                $f('moving_time', (string) __('app.tracking.report_col_moving_time')),
                $f('odometer_start', (string) __('app.tracking.report_col_odometer_start')),
                $f('odometer_end', (string) __('app.tracking.report_col_odometer_end')),
                $f('odometer_delta', (string) __('app.tracking.report_col_odometer_delta')),
                $f('start_time', (string) __('app.tracking.report_col_start_time')),
                $f('end_time', (string) __('app.tracking.report_col_end_time')),
            ],
            'diesel' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('segment', (string) __('app.tracking.report_col_segment')),
                $f('start', (string) __('app.tracking.report_col_start')),
                $f('end', (string) __('app.tracking.report_col_end')),
                $f('distance_km', (string) __('app.tracking.report_col_distance_km')),
                $f('fuel_liters', (string) __('app.tracking.report_col_fuel_liters')),
                $f('efficiency', (string) __('app.tracking.report_col_efficiency')),
                $f('fuel_method', (string) __('app.tracking.report_col_fuel_method')),
                $f('rate_l_100', (string) __('app.tracking.report_col_rate_l_100')),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'events' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('event_type', (string) __('app.tracking.report_col_event_type')),
                $f('title', (string) __('app.tracking.report_col_title')),
                $f('message', (string) __('app.tracking.report_col_message')),
                $f('geofence', (string) __('app.tracking.report_col_geofence')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
                $f('speed', (string) __('app.tracking.report_col_speed')),
            ],
            'positions' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
                $f('speed', (string) __('app.tracking.report_col_speed')),
                $f('heading', (string) __('app.tracking.report_col_heading')),
                $f('ignition', (string) __('app.tracking.report_col_ignition')),
                $f('status', (string) __('app.tracking.report_col_status')),
            ],
            'route' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('points', (string) __('app.tracking.report_col_points')),
                $f('distance_km', (string) __('app.tracking.report_col_distance_km')),
                $f('moving_time', (string) __('app.tracking.report_col_moving_time')),
                $f('stopped_time', (string) __('app.tracking.report_col_stopped_time')),
                $f('idle_time', (string) __('app.tracking.report_col_idle_time')),
                $f('parking_time', (string) __('app.tracking.report_col_parking_time')),
                $f('offline_time', (string) __('app.tracking.report_col_offline_time')),
                $f('max_speed', (string) __('app.tracking.report_col_max_speed')),
                $f('avg_speed', (string) __('app.tracking.report_col_avg_speed')),
                $f('trips', (string) __('app.tracking.report_col_trips')),
                $f('start_time', (string) __('app.tracking.report_col_start_time')),
                $f('end_time', (string) __('app.tracking.report_col_end_time')),
                $f('duration', (string) __('app.tracking.report_col_duration')),
            ],
            'overspeeds' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('start', (string) __('app.tracking.report_col_start')),
                $f('end', (string) __('app.tracking.report_col_end')),
                $f('duration', (string) __('app.tracking.report_col_duration')),
                $f('max_speed', (string) __('app.tracking.report_col_max_speed')),
                $f('speed_limit', (string) __('app.tracking.report_col_speed_limit')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'zone_inout' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('event_type', (string) __('app.tracking.report_col_event_type')),
                $f('geofence', (string) __('app.tracking.report_col_geofence')),
                $f('title', (string) __('app.tracking.report_col_title')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'fuel_fillings' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('fuel_liters', (string) __('app.tracking.report_col_fuel_liters')),
                $f('fuel_before', (string) __('app.tracking.report_col_fuel_before')),
                $f('fuel_after', (string) __('app.tracking.report_col_fuel_after')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'current_position' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('driver', self::driver()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('status', (string) __('app.tracking.report_col_status')),
                $f('speed', (string) __('app.tracking.report_col_speed')),
                $f('heading', (string) __('app.tracking.report_col_heading')),
                $f('altitude', (string) __('app.tracking.report_col_altitude')),
                $f('ignition', (string) __('app.tracking.report_col_ignition')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('address', self::address()),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'object_info' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('driver', self::driver()),
                $f('imei', (string) __('app.tracking.report_col_imei')),
                $f('model', (string) __('app.tracking.report_col_model')),
                $f('phone', (string) __('app.tracking.report_col_phone')),
                $f('status', (string) __('app.tracking.report_col_status')),
                $f('last_update', (string) __('app.tracking.report_col_last_update')),
                $f('speed', (string) __('app.tracking.report_col_speed')),
                $f('ignition', (string) __('app.tracking.report_col_ignition')),
                $f('odometer', (string) __('app.tracking.report_col_odometer')),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'service' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('service_name', (string) __('app.tracking.report_col_service_name')),
                $f('summary', (string) __('app.tracking.report_col_summary')),
                $f('status', (string) __('app.tracking.report_col_status')),
                $f('odometer', (string) __('app.tracking.report_col_odometer')),
                $f('odometer_left', (string) __('app.tracking.report_col_odometer_left')),
                $f('days_left', (string) __('app.tracking.report_col_days_left')),
            ],
            'tasks' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('task_name', (string) __('app.tracking.report_col_task_name')),
                $f('task_start', (string) __('app.tracking.report_col_task_start')),
                $f('task_destination', (string) __('app.tracking.report_col_task_destination')),
                $f('priority', (string) __('app.tracking.report_col_priority')),
                $f('status', (string) __('app.tracking.report_col_status')),
                $f('start', (string) __('app.tracking.report_col_start')),
                $f('end', (string) __('app.tracking.report_col_end')),
            ],
            'speed' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('speed', (string) __('app.tracking.report_col_speed')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'altitude' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('altitude', (string) __('app.tracking.report_col_altitude')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            'ignition' => [
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('time', (string) __('app.tracking.report_col_time')),
                $f('ignition', (string) __('app.tracking.report_col_ignition')),
                $f('lat', (string) __('app.tracking.report_col_lat')),
                $f('lng', (string) __('app.tracking.report_col_lng')),
                $f('maps', (string) __('app.tracking.report_col_maps')),
            ],
            default => [ // summary
                $f('vehicle', self::vehicle()),
                $f('plate', self::plate()),
                $f('driver', self::driver()),
                $f('distance_km', (string) __('app.tracking.report_col_distance_km')),
                $f('moving_time', (string) __('app.tracking.report_col_moving_time')),
                $f('stopped_time', (string) __('app.tracking.report_col_stopped_time')),
                $f('idle_time', (string) __('app.tracking.report_col_idle_time')),
                $f('parking_time', (string) __('app.tracking.report_col_parking_time')),
                $f('offline_time', (string) __('app.tracking.report_col_offline_time')),
                $f('max_speed', (string) __('app.tracking.report_col_max_speed')),
                $f('avg_speed', (string) __('app.tracking.report_col_avg_speed')),
                $f('stops', (string) __('app.tracking.report_col_stops')),
                $f('trips', (string) __('app.tracking.report_col_trips')),
                $f('overspeed', (string) __('app.tracking.report_col_overspeed')),
                $f('start_time', (string) __('app.tracking.report_col_start_time')),
                $f('end_time', (string) __('app.tracking.report_col_end_time')),
                $f('duration', (string) __('app.tracking.report_col_duration')),
            ],
        };
    }

    /**
     * @param  list<string>|null  $fieldKeys
     * @return list<string>
     */
    public static function labelsForFieldKeys(string $type, ?array $fieldKeys): array
    {
        if ($fieldKeys === null || $fieldKeys === []) {
            return [];
        }

        $byKey = [];
        foreach (self::fieldsForType($type) as $field) {
            $byKey[$field['key']] = $field['label'];
        }

        $labels = [];
        foreach ($fieldKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && isset($byKey[$key])) {
                $labels[] = $byKey[$key];
            }
        }

        return $labels;
    }

    /**
     * @param  list<list<mixed>>  $rows  header + data
     * @param  list<string>|null  $fieldKeys
     * @return list<list<mixed>>
     */
    public static function projectRowsByFieldKeys(string $type, array $rows, ?array $fieldKeys, ?array $filters = null): array
    {
        if ($fieldKeys === null || $rows === []) {
            return $rows;
        }

        // Explicit empty selection (or all keys invalid after sanitize) — never leak full table.
        if ($fieldKeys === []) {
            return array_map(static fn () => [], $rows);
        }

        $wantedLabels = self::labelsForFieldKeys($type, $fieldKeys);
        if ($wantedLabels === []) {
            return array_map(static fn () => [], $rows);
        }

        // Prefer projecting against the actual header (includes filter-injected address cols).
        $header = array_map(static fn ($c) => (string) $c, $rows[0]);
        $indices = [];
        foreach ($wantedLabels as $label) {
            $idx = array_search($label, $header, true);
            if ($idx !== false) {
                $indices[] = (int) $idx;
            }
        }

        if ($indices === []) {
            return array_map(static fn () => [], $rows);
        }

        return array_map(static function (array $row) use ($indices): array {
            $out = [];
            foreach ($indices as $i) {
                $out[] = $row[$i] ?? '';
            }

            return $out;
        }, $rows);
    }

    /**
     * Keep only field keys that exist for the report type (stable order preserved).
     *
     * @param  list<string>|null  $fieldKeys
     * @return list<string>|null
     */
    public static function sanitizeFieldKeys(string $type, ?array $fieldKeys): ?array
    {
        if ($fieldKeys === null) {
            return null;
        }

        $allowed = [];
        foreach (self::fieldsForType($type) as $field) {
            $allowed[$field['key']] = true;
        }

        $out = [];
        foreach ($fieldKeys as $key) {
            $key = trim((string) $key);
            if ($key !== '' && isset($allowed[$key]) && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>|string|null  $raw
     * @return list<string>|null
     */
    public static function parseFieldKeys(array|string|null $raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_string($raw)) {
            $parts = preg_split('/[,\s]+/', $raw) ?: [];
        } else {
            $parts = $raw;
        }

        $keys = [];
        foreach ($parts as $part) {
            $key = trim((string) $part);
            if ($key !== '' && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys === [] ? null : $keys;
    }

    /**
     * @param  array<string, mixed>|null  $filters  ReportFilters::toArray() shape
     * @return list<string>
     */
    public static function columnsForType(string $type, ?array $filters = null): array
    {
        $columns = match ($type) {
            'trips' => [
                self::vehicle(),
                self::plate(),
                self::driver(),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
                (string) __('app.tracking.report_col_start_lat'),
                (string) __('app.tracking.report_col_start_lng'),
                (string) __('app.tracking.report_col_end_lat'),
                (string) __('app.tracking.report_col_end_lng'),
                (string) __('app.tracking.report_col_maps_start'),
                (string) __('app.tracking.report_col_maps_end'),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_moving_time'),
                (string) __('app.tracking.report_col_stops'),
                (string) __('app.tracking.report_col_route_points'),
                (string) __('app.tracking.report_col_max_speed'),
                (string) __('app.tracking.report_col_avg_speed'),
            ],
            'stops' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_status'),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'trips_stops' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_segment'),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
                (string) __('app.tracking.report_col_stops'),
            ],
            'mileage' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_date'),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_points'),
                (string) __('app.tracking.report_col_start_time'),
                (string) __('app.tracking.report_col_end_time'),
                (string) __('app.tracking.report_col_maps_start'),
                (string) __('app.tracking.report_col_maps_end'),
            ],
            'odometer' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_moving_time'),
                (string) __('app.tracking.report_col_odometer_start'),
                (string) __('app.tracking.report_col_odometer_end'),
                (string) __('app.tracking.report_col_odometer_delta'),
                (string) __('app.tracking.report_col_start_time'),
                (string) __('app.tracking.report_col_end_time'),
            ],
            'diesel' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_segment'),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_fuel_liters'),
                (string) __('app.tracking.report_col_efficiency'),
                (string) __('app.tracking.report_col_fuel_method'),
                (string) __('app.tracking.report_col_rate_l_100'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'events' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_event_type'),
                (string) __('app.tracking.report_col_title'),
                (string) __('app.tracking.report_col_message'),
                (string) __('app.tracking.report_col_geofence'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
                (string) __('app.tracking.report_col_speed'),
            ],
            'positions' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
                (string) __('app.tracking.report_col_speed'),
                (string) __('app.tracking.report_col_heading'),
                (string) __('app.tracking.report_col_ignition'),
                (string) __('app.tracking.report_col_status'),
            ],
            'route' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_points'),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_moving_time'),
                (string) __('app.tracking.report_col_stopped_time'),
                (string) __('app.tracking.report_col_idle_time'),
                (string) __('app.tracking.report_col_parking_time'),
                (string) __('app.tracking.report_col_offline_time'),
                (string) __('app.tracking.report_col_max_speed'),
                (string) __('app.tracking.report_col_avg_speed'),
                (string) __('app.tracking.report_col_trips'),
                (string) __('app.tracking.report_col_start_time'),
                (string) __('app.tracking.report_col_end_time'),
                (string) __('app.tracking.report_col_duration'),
            ],
            'overspeeds' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_max_speed'),
                (string) __('app.tracking.report_col_speed_limit'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'zone_inout' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_event_type'),
                (string) __('app.tracking.report_col_geofence'),
                (string) __('app.tracking.report_col_title'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'fuel_fillings' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_fuel_liters'),
                (string) __('app.tracking.report_col_fuel_before'),
                (string) __('app.tracking.report_col_fuel_after'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'current_position' => [
                self::vehicle(),
                self::plate(),
                self::driver(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_status'),
                (string) __('app.tracking.report_col_speed'),
                (string) __('app.tracking.report_col_heading'),
                (string) __('app.tracking.report_col_altitude'),
                (string) __('app.tracking.report_col_ignition'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'object_info' => [
                self::vehicle(),
                self::plate(),
                self::driver(),
                (string) __('app.tracking.report_col_imei'),
                (string) __('app.tracking.report_col_model'),
                (string) __('app.tracking.report_col_phone'),
                (string) __('app.tracking.report_col_status'),
                (string) __('app.tracking.report_col_last_update'),
                (string) __('app.tracking.report_col_speed'),
                (string) __('app.tracking.report_col_ignition'),
                (string) __('app.tracking.report_col_odometer'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'service' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_service_name'),
                (string) __('app.tracking.report_col_summary'),
                (string) __('app.tracking.report_col_status'),
                (string) __('app.tracking.report_col_odometer'),
                (string) __('app.tracking.report_col_odometer_left'),
                (string) __('app.tracking.report_col_days_left'),
            ],
            'tasks' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_task_name'),
                (string) __('app.tracking.report_col_task_start'),
                (string) __('app.tracking.report_col_task_destination'),
                (string) __('app.tracking.report_col_priority'),
                (string) __('app.tracking.report_col_status'),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
            ],
            'speed' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_speed'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'altitude' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_altitude'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
            ],
            'ignition' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_ignition'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_maps'),
            ],
            default => [
                self::vehicle(),
                self::plate(),
                self::driver(),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_moving_time'),
                (string) __('app.tracking.report_col_stopped_time'),
                (string) __('app.tracking.report_col_idle_time'),
                (string) __('app.tracking.report_col_parking_time'),
                (string) __('app.tracking.report_col_offline_time'),
                (string) __('app.tracking.report_col_max_speed'),
                (string) __('app.tracking.report_col_avg_speed'),
                (string) __('app.tracking.report_col_stops'),
                (string) __('app.tracking.report_col_trips'),
                (string) __('app.tracking.report_col_overspeed'),
                (string) __('app.tracking.report_col_start_time'),
                (string) __('app.tracking.report_col_end_time'),
                (string) __('app.tracking.report_col_duration'),
            ],
        };

        return self::applyLocationFilterColumns($type, $columns, $filters);
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, mixed>|null  $filters
     * @return list<string>
     */
    public static function applyLocationFilterColumns(string $type, array $columns, ?array $filters): array
    {
        if ($filters === null) {
            return $columns;
        }

        $showCoordinates = ($filters['show_coordinates'] ?? true) !== false;
        $showAddress = ! empty($filters['show_addresses'])
            || ! empty($filters['markers_instead_of_addresses'])
            || ! empty($filters['zones_instead_of_addresses']);

        if (! $showCoordinates) {
            $hide = [
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_start_lat'),
                (string) __('app.tracking.report_col_start_lng'),
                (string) __('app.tracking.report_col_end_lat'),
                (string) __('app.tracking.report_col_end_lng'),
            ];
            $columns = array_values(array_filter(
                $columns,
                static fn (string $col) => ! in_array($col, $hide, true),
            ));
        }

        if ($showAddress) {
            $maps = (string) __('app.tracking.report_col_maps');
            $mapsStart = (string) __('app.tracking.report_col_maps_start');
            $insertAt = count($columns);
            foreach ($columns as $i => $col) {
                if ($col === $maps || $col === $mapsStart) {
                    $insertAt = $i;
                    break;
                }
            }

            if ($type === 'trips') {
                array_splice($columns, $insertAt, 0, [self::startAddress(), self::endAddress()]);
            } else {
                array_splice($columns, $insertAt, 0, [self::address()]);
            }
        }

        return $columns;
    }

    /**
     * @return array<string, string>
     */
    public static function jsBundle(): array
    {
        return [
            'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'vehicle' => self::vehicle(),
            'noData' => (string) __('app.tracking.report_no_data'),
            'loadFailed' => (string) __('app.tracking.report_load_failed'),
            'rowsPerPage' => (string) __('app.tracking.report_rows_per_page'),
            'pagerRange' => (string) __('app.tracking.report_pager_range'),
            'pageOf' => (string) __('app.tracking.report_page_of'),
            'kpiDistance' => (string) __('app.tracking.report_kpi_distance'),
            'kpiMoving' => (string) __('app.tracking.report_kpi_moving'),
            'kpiStopped' => (string) __('app.tracking.report_kpi_stopped'),
            'kpiTrips' => (string) __('app.tracking.report_kpi_trips'),
            'kpiStops' => (string) __('app.tracking.report_kpi_stops'),
            'kpiEvents' => (string) __('app.tracking.report_kpi_events'),
            'kpiPoints' => (string) __('app.tracking.report_kpi_points'),
            'kpiMaxSpeed' => (string) __('app.tracking.report_kpi_max_speed'),
            'kpiDevices' => (string) __('app.tracking.report_kpi_devices'),
            'kpiDays' => (string) __('app.tracking.report_kpi_days'),
            'selectAll' => (string) __('app.tracking.report_select_all'),
            'selectNone' => (string) __('app.tracking.report_select_none'),
            'selectVehicle' => (string) __('app.tracking.report_select_vehicle'),
            'customPickOne' => (string) __('app.tracking.report_custom_pick_one'),
            'customSelectAll' => (string) __('app.tracking.report_custom_select_all'),
            'customClear' => (string) __('app.tracking.report_custom_clear'),
            'devicesCapped' => (string) __('app.tracking.report_devices_capped'),
            'positionsTruncated' => (string) __('app.tracking.report_positions_truncated'),
            'analyticsDownsampled' => (string) __('app.tracking.report_analytics_downsampled'),
            'colAddress' => self::address(),
            'colStartAddress' => self::startAddress(),
            'colEndAddress' => self::endAddress(),
            'coordLabels' => [
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
                (string) __('app.tracking.report_col_start_lat'),
                (string) __('app.tracking.report_col_start_lng'),
                (string) __('app.tracking.report_col_end_lat'),
                (string) __('app.tracking.report_col_end_lng'),
            ],
            'coordFieldKeys' => ['lat', 'lng', 'start_lat', 'start_lng', 'end_lat', 'end_lng'],
            'addressFieldKeys' => ['address', 'start_address', 'end_address'],
            'filterApplicability' => ReportFilters::applicabilityMap(),
            'fields' => [
                'summary' => self::fieldsForType('summary'),
                'trips' => self::fieldsForType('trips'),
                'stops' => self::fieldsForType('stops'),
                'trips_stops' => self::fieldsForType('trips_stops'),
                'mileage' => self::fieldsForType('mileage'),
                'odometer' => self::fieldsForType('odometer'),
                'diesel' => self::fieldsForType('diesel'),
                'events' => self::fieldsForType('events'),
                'route' => self::fieldsForType('route'),
                'positions' => self::fieldsForType('positions'),
                'overspeeds' => self::fieldsForType('overspeeds'),
                'zone_inout' => self::fieldsForType('zone_inout'),
                'fuel_fillings' => self::fieldsForType('fuel_fillings'),
                'current_position' => self::fieldsForType('current_position'),
                'object_info' => self::fieldsForType('object_info'),
                'service' => self::fieldsForType('service'),
                'tasks' => self::fieldsForType('tasks'),
                'speed' => self::fieldsForType('speed'),
                'altitude' => self::fieldsForType('altitude'),
                'ignition' => self::fieldsForType('ignition'),
            ],
            'columns' => [
                'summary' => self::columnsForType('summary'),
                'trips' => self::columnsForType('trips'),
                'stops' => self::columnsForType('stops'),
                'trips_stops' => self::columnsForType('trips_stops'),
                'mileage' => self::columnsForType('mileage'),
                'odometer' => self::columnsForType('odometer'),
                'diesel' => self::columnsForType('diesel'),
                'events' => self::columnsForType('events'),
                'route' => self::columnsForType('route'),
                'positions' => self::columnsForType('positions'),
                'overspeeds' => self::columnsForType('overspeeds'),
                'zone_inout' => self::columnsForType('zone_inout'),
                'fuel_fillings' => self::columnsForType('fuel_fillings'),
                'current_position' => self::columnsForType('current_position'),
                'object_info' => self::columnsForType('object_info'),
                'service' => self::columnsForType('service'),
                'tasks' => self::columnsForType('tasks'),
                'speed' => self::columnsForType('speed'),
                'altitude' => self::columnsForType('altitude'),
                'ignition' => self::columnsForType('ignition'),
            ],
            'openMaps' => (string) __('app.tracking.report_open_maps'),
            'kpiFuel' => (string) __('app.tracking.report_kpi_fuel'),
            'kpiEfficiency' => (string) __('app.tracking.report_kpi_efficiency'),
            'segPeriod' => (string) __('app.tracking.report_seg_period'),
            'segTrip' => (string) __('app.tracking.report_seg_trip'),
            'segDay' => (string) __('app.tracking.report_seg_day'),
        ];
    }
}
