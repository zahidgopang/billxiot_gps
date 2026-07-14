<?php

namespace Tests\Unit;

use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Services\Tracking\Reports\ReportExportService;
use App\Services\Tracking\Reports\ReportLabels;
use App\Support\Tracking\HistoryRangeBounds;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    public function test_table_rows_cover_all_report_types(): void
    {
        $service = new ReportExportService;

        foreach (['summary', 'trips', 'stops', 'trips_stops', 'mileage', 'diesel', 'events', 'route', 'positions'] as $type) {
            $report = $this->sampleReport($type);
            $rows = $service->tableRows($report, $type);

            $this->assertNotEmpty($rows, "Expected header row for {$type}");
            $this->assertSame(ReportLabels::columnsForType($type), $rows[0]);
            $this->assertGreaterThan(1, count($rows), "Expected data rows for {$type}");
        }
    }

    public function test_arabic_export_headers_use_translated_columns(): void
    {
        App::setLocale('ar');

        $service = new ReportExportService;
        $rows = $service->tableRows($this->sampleReport('summary'), 'summary');

        $this->assertSame('المركبة', $rows[0][0]);
        $this->assertSame('المسافة (كم)', $rows[0][3]);
        $this->assertStringContainsString('س', ReportLabels::formatDuration(3665));
        $this->assertSame('تشغيل', ReportLabels::formatIgnition(true));
    }

    public function test_english_export_headers_remain_english(): void
    {
        App::setLocale('en');

        $service = new ReportExportService;
        $rows = $service->tableRows($this->sampleReport('summary'), 'summary');

        $this->assertSame('Vehicle', $rows[0][0]);
        $this->assertSame('Distance (km)', $rows[0][3]);
        $this->assertSame('On', ReportLabels::formatIgnition(true));
    }

    public function test_report_datetime_range_preserves_selected_times(): void
    {
        $helper = $this->historyRangeHelper();
        $tz = (string) config('app.timezone', 'Asia/Riyadh');

        $from = $helper->parse('2026-07-06 08:30', $tz, true);
        $to = $helper->parse('2026-07-06 18:45', $tz, false);

        $this->assertSame('08:30:00', $from->format('H:i:s'));
        $this->assertSame('18:45:00', $to->format('H:i:s'));
        $this->assertFalse($helper->isDateOnly('2026-07-06 08:30'));
    }

    public function test_report_date_only_range_expands_to_calendar_days(): void
    {
        $helper = $this->historyRangeHelper();
        $tz = (string) config('app.timezone', 'Asia/Riyadh');

        $from = $helper->parse('2026-07-06', $tz, true);
        $to = $helper->parse('2026-07-07', $tz, false);
        $range = HistoryRangeBounds::normalize($from, $to);

        $this->assertTrue(HistoryRangeBounds::isCalendarDayStart($range['from']));
        $this->assertTrue(HistoryRangeBounds::isCalendarDayEnd($range['to']));
    }

    private function historyRangeHelper(): object
    {
        return new class
        {
            use ResolvesHistoryDateRange;

            public function parse(string $value, string $tz, bool $start)
            {
                return $this->parseHistoryDate($value, $tz, $start);
            }

            public function isDateOnly(string $value): bool
            {
                return $this->isDateOnlyInput($value);
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleReport(string $type): array
    {
        $device = [
            'device_id' => 1,
            'device_name' => 'Vehicle A',
            'plate' => 'ABC 123',
            'driver' => 'Driver',
            'total_distance_km' => 12.5,
            'moving_time_seconds' => 3665,
            'stopped_time_seconds' => 900,
            'idle_time_seconds' => 600,
            'parking_time_seconds' => 300,
            'offline_time_seconds' => 0,
            'max_speed_kmh' => 80,
            'average_speed_kmh' => 45,
            'stop_count' => 1,
            'trip_count' => 1,
            'overspeed_events' => 0,
            'start_time' => '2026-07-01T08:00:00+03:00',
            'end_time' => '2026-07-01T18:00:00+03:00',
            'total_duration_seconds' => 36000,
            'point_count' => 120,
            'trips' => [[
                'start_time' => '08:00',
                'end_time' => '09:00',
                'start_lat' => 21.4,
                'start_lng' => 39.8,
                'end_lat' => 21.5,
                'end_lng' => 39.9,
                'start_maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.400000,39.800000',
                'end_maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.500000,39.900000',
                'distance_km' => 12.5,
                'duration_seconds' => 3600,
                'moving_time_seconds' => 3000,
                'stop_count' => 1,
                'route_point_count' => 10,
                'max_speed_kmh' => 80,
                'average_speed_kmh' => 45,
            ]],
            'stops' => [[
                'status_label' => 'Idle',
                'start_display' => '09:00',
                'end_display' => '09:15',
                'duration_seconds' => 900,
                'lat' => 21.5,
                'lng' => 39.9,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.500000,39.900000',
            ]],
            'segments' => [[
                'kind' => 'trip',
                'kind_label' => 'Trip',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'duration_seconds' => 3600,
                'distance_km' => 12.5,
                'start_lat' => 21.4,
                'start_lng' => 39.8,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.400000,39.800000',
                'stop_count' => 1,
            ], [
                'kind' => 'stop',
                'kind_label' => 'Stop',
                'start_display' => '09:00',
                'end_display' => '09:15',
                'duration_seconds' => 900,
                'lat' => 21.5,
                'lng' => 39.9,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.500000,39.900000',
            ]],
            'days' => [[
                'date' => '2026-07-01',
                'distance_km' => 12.5,
                'duration_seconds' => 36000,
                'point_count' => 120,
                'start_time' => '2026-07-01T08:00:00+03:00',
                'end_time' => '2026-07-01T18:00:00+03:00',
                'start_maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.400000,39.800000',
                'end_maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.500000,39.900000',
                'fuel_liters' => 1.5,
                'fuel_method' => 'estimated',
                'efficiency' => 12.0,
            ]],
            'fuel_liters' => 1.5,
            'fuel_method' => 'estimated',
            'fuel_method_label' => 'Estimated (rate × distance)',
            'efficiency' => 12.0,
            'efficiency_label' => 'L/100km',
            'rate_l_per_100km' => 12.0,
            'events' => [[
                'time_display' => '10:00',
                'event_type' => 'overspeed',
                'title' => 'Speed',
                'message' => 'Over limit',
                'geofence' => '',
                'lat' => 21.5,
                'lng' => 39.9,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.500000,39.900000',
                'speed' => 95,
            ]],
            'positions' => [[
                'time_display' => '08:05',
                'lat' => 21.4,
                'lng' => 39.8,
                'maps_url' => 'https://www.google.com/maps/search/?api=1&query=21.400000,39.800000',
                'speed' => 40,
                'heading' => 90,
                'ignition' => true,
                'status' => 'Moving',
            ]],
        ];

        return [
            'type' => $type,
            'from' => '2026-07-01T00:00:00+03:00',
            'to' => '2026-07-08T23:59:59+03:00',
            'devices' => [$device],
        ];
    }
}
