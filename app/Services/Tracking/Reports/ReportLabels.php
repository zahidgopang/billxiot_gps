<?php

namespace App\Services\Tracking\Reports;

final class ReportLabels
{
    public static function vehicle(): string
    {
        return (string) __('app.tracking.report_col_vehicle');
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

    /**
     * @return list<string>
     */
    public static function columnsForType(string $type): array
    {
        return match ($type) {
            'trips' => [
                self::vehicle(),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_moving_time'),
                (string) __('app.tracking.report_col_max_speed'),
                (string) __('app.tracking.report_col_avg_speed'),
            ],
            'stops' => [
                self::vehicle(),
                (string) __('app.tracking.report_col_start'),
                (string) __('app.tracking.report_col_end'),
                (string) __('app.tracking.report_col_duration'),
                (string) __('app.tracking.report_col_lat'),
                (string) __('app.tracking.report_col_lng'),
            ],
            'events' => [
                self::vehicle(),
                (string) __('app.tracking.report_col_time'),
                (string) __('app.tracking.report_col_event_type'),
                (string) __('app.tracking.report_col_title'),
                (string) __('app.tracking.report_col_message'),
                (string) __('app.tracking.report_col_speed'),
            ],
            'route' => [
                self::vehicle(),
                (string) __('app.tracking.report_col_points'),
                (string) __('app.tracking.report_col_distance_km'),
            ],
            default => [
                self::vehicle(),
                (string) __('app.tracking.report_col_distance_km'),
                (string) __('app.tracking.report_col_moving_time'),
                (string) __('app.tracking.report_col_stopped_time'),
                (string) __('app.tracking.report_col_max_speed'),
                (string) __('app.tracking.report_col_avg_speed'),
                (string) __('app.tracking.report_col_stops'),
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
            'columns' => [
                'summary' => self::columnsForType('summary'),
                'trips' => self::columnsForType('trips'),
                'stops' => self::columnsForType('stops'),
                'events' => self::columnsForType('events'),
                'route' => self::columnsForType('route'),
            ],
        ];
    }
}
