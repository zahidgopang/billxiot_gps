<?php

namespace Tests\Unit;

use App\Services\Tracking\HistoryAnalyticsService;
use Carbon\Carbon;
use Tests\TestCase;

class HistoryAnalyticsServiceTest extends TestCase
{
    public function test_analyze_never_returns_negative_durations(): void
    {
        $service = new HistoryAnalyticsService;

        $points = collect([
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:00:00'),
            ],
            (object) [
                'lat' => 21.41,
                'lng' => 39.81,
                'speed' => 45,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:18:00'),
            ],
            (object) [
                'lat' => 21.42,
                'lng' => 39.82,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:24:00'),
            ],
        ]);

        $stats = $service->analyze($points);

        $this->assertGreaterThanOrEqual(0, $stats['moving_time_seconds']);
        $this->assertGreaterThanOrEqual(0, $stats['idle_time_seconds']);
        $this->assertGreaterThanOrEqual(0, $stats['stopped_time_seconds']);
        $this->assertGreaterThanOrEqual(0, $stats['total_duration_seconds']);
    }

    public function test_timeline_uses_ignition_aware_labels(): void
    {
        $service = new HistoryAnalyticsService;

        $points = collect([
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 40,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:00:00'),
            ],
            (object) [
                'lat' => 21.45,
                'lng' => 39.85,
                'speed' => 42,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:10:00'),
            ],
            (object) [
                'lat' => 21.45,
                'lng' => 39.85,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:16:00'),
            ],
        ]);

        $timeline = $service->buildTimeline($points);

        $this->assertNotEmpty($timeline);
        $this->assertSame('running', $timeline[0]['status_key']);
        $this->assertGreaterThan(0, $timeline[0]['duration_seconds']);
    }

    public function test_history_statuses_include_moving_idle_and_parking_with_durations(): void
    {
        $service = new HistoryAnalyticsService;

        $points = collect([
            (object) [
                'id' => 1,
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 35,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:00:00'),
            ],
            (object) [
                'id' => 2,
                'lat' => 21.41,
                'lng' => 39.81,
                'speed' => 20,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:05:00'),
            ],
            (object) [
                'id' => 3,
                'lat' => 21.41,
                'lng' => 39.81,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:08:00'),
            ],
            (object) [
                'id' => 4,
                'lat' => 21.41,
                'lng' => 39.81,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:12:00'),
            ],
            (object) [
                'id' => 5,
                'lat' => 21.41,
                'lng' => 39.81,
                'speed' => 0,
                'ignition' => false,
                'recorded_at' => Carbon::parse('2026-07-03 08:20:00'),
            ],
        ]);

        $stats = $service->analyze($points);
        $statuses = $service->pointStatuses($points);

        $this->assertSame(300, $stats['moving_time_seconds']);
        $this->assertSame(420, $stats['idle_time_seconds']);
        $this->assertSame(480, $stats['parking_time_seconds']);
        $this->assertSame(900, $stats['stopped_time_seconds']);

        $this->assertSame(['moving', 'moving', 'idle', 'idle', 'parking'], array_column($statuses, 'status_key'));
        $this->assertSame('idle', $statuses[2]['motion_status_key']);
        $this->assertSame(240, $statuses[3]['status_duration_seconds']);
        $this->assertSame('parking', $statuses[4]['trip_status_key']);
    }

    public function test_long_idle_timeline_becomes_stopped_marker(): void
    {
        $service = new HistoryAnalyticsService;

        // Keep gaps under offline threshold (10 min) so the run merges as idle, then upgrades to stopped.
        $points = collect([
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:00:00'),
            ],
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:04:00'),
            ],
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:08:00'),
            ],
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 0,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:12:00'),
            ],
        ]);

        $timeline = $service->buildTimeline($points);
        $this->assertNotEmpty($timeline);
        $this->assertSame('stopped', $timeline[0]['status_key']);
        $this->assertGreaterThanOrEqual(600, $timeline[0]['duration_seconds']);
    }

    public function test_gap_over_ten_minutes_creates_offline_segment(): void
    {
        $service = new HistoryAnalyticsService;

        $points = collect([
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 20,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:00:00'),
            ],
            (object) [
                'lat' => 21.5,
                'lng' => 39.9,
                'speed' => 20,
                'ignition' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:15:00'),
            ],
        ]);

        $timeline = $service->buildTimeline($points);
        $keys = array_column($timeline, 'status_key');
        $this->assertContains('offline', $keys);
    }

    public function test_acc_is_used_when_ignition_missing(): void
    {
        $service = new HistoryAnalyticsService;

        $points = collect([
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 0,
                'acc' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:00:00'),
            ],
            (object) [
                'lat' => 21.4,
                'lng' => 39.8,
                'speed' => 0,
                'acc' => true,
                'recorded_at' => Carbon::parse('2026-07-03 08:05:00'),
            ],
        ]);

        $timeline = $service->buildTimeline($points);
        $this->assertSame('idle', $timeline[0]['status_key']);
    }
}
