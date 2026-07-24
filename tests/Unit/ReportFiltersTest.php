<?php

namespace Tests\Unit;

use App\Services\Tracking\HistoryAnalyticsService;
use App\Services\Tracking\Reports\ReportFilters;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportFiltersTest extends TestCase
{
    public function test_defaults_preserve_legacy_stop_threshold(): void
    {
        $filters = ReportFilters::defaults();

        $this->assertFalse($filters->ignoreEmpty);
        $this->assertTrue($filters->showCoordinates);
        $this->assertFalse($filters->showAddresses);
        $this->assertSame(HistoryAnalyticsService::STOP_MIN_SECONDS, $filters->stopMinSeconds);
        $this->assertNull($filters->speedLimitKmh);
    }

    public function test_from_request_parses_web_filters(): void
    {
        $request = Request::create('/tracking/reports/generate', 'GET', [
            'ignore_empty' => '1',
            'show_coordinates' => '0',
            'show_addresses' => '1',
            'markers_instead_of_addresses' => '1',
            'zones_instead_of_addresses' => '0',
            'stop_min_seconds' => '300',
            'speed_limit_kmh' => '90',
        ]);

        $filters = ReportFilters::fromRequest($request);

        $this->assertTrue($filters->ignoreEmpty);
        $this->assertFalse($filters->showCoordinates);
        $this->assertTrue($filters->showAddresses);
        $this->assertTrue($filters->markersInsteadOfAddresses);
        $this->assertFalse($filters->zonesInsteadOfAddresses);
        $this->assertSame(300, $filters->stopMinSeconds);
        $this->assertSame(90.0, $filters->speedLimitKmh);
        $this->assertTrue($filters->needsLocationLabels());
    }

    public function test_applicability_keeps_core_filters_visible(): void
    {
        $summary = ReportFilters::applicableKeys('summary');
        $this->assertContains('ignore_empty', $summary);
        $this->assertContains('show_coordinates', $summary);
        $this->assertContains('show_addresses', $summary);
        $this->assertContains('markers_instead_of_addresses', $summary);
        $this->assertContains('zones_instead_of_addresses', $summary);
        $this->assertContains('stops', $summary);
        $this->assertContains('speed_limit', ReportFilters::applicableKeys('overspeeds'));
        $this->assertNotContains('speed_limit', ReportFilters::applicableKeys('stops'));
    }
}
