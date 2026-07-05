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

    /**
     * @return list<string>
     */
    public static function columnsForType(string $type): array
    {
        return match ($type) {
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
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_moving_time'),
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
                (string) __('app.tracking.report_col_speed'),
            ],
            'positions' => [
                self::vehicle(),
                self::plate(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
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
        'selectAll' => (string) __('app.tracking.report_select_all'),
        'selectNone' => (string) __('app.tracking.report_select_none'),
        'selectVehicle' => (string) __('app.tracking.report_select_vehicle'),
        'devicesCapped' => (string) __('app.tracking.report_devices_capped'),
        'positionsTruncated' => (string) __('app.tracking.report_positions_truncated'),
        'columns' => [
                'summary' => self::columnsForType('summary'),
                'trips' => self::columnsForType('trips'),
                'stops' => self::columnsForType('stops'),
                'events' => self::columnsForType('events'),
                'route' => self::columnsForType('route'),
                'positions' => self::columnsForType('positions'),
            ],
        ];
    }
}
