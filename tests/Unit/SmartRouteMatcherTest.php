<?php

namespace Tests\Unit;

use App\Models\RoutePlan;
use App\Services\Routes\SmartRouteMatcher;
use Tests\TestCase;

class SmartRouteMatcherTest extends TestCase
{
    public function test_vehicle_within_tolerance_is_on_assigned_route(): void
    {
        $route = $this->sampleRoute();
        $matcher = new SmartRouteMatcher();
        $path = [
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ];

        $result = $matcher->evaluate($route, $path, 21.4225, 39.8262, 80, null, []);

        $this->assertSame(SmartRouteMatcher::STATE_ON_ASSIGNED, $result['navigation_state']);
        $this->assertTrue($result['on_route']);
        $this->assertGreaterThanOrEqual(0, $result['percentage_completed']);
    }

    public function test_vehicle_heading_toward_destination_can_join_mid_route(): void
    {
        $route = $this->sampleRoute();
        $matcher = new SmartRouteMatcher();
        $path = [
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ];

        $context = ['last_dest_distance_m' => 500000];
        $result = $matcher->evaluate($route, $path, 21.50, 39.85, 90, 15, $context);

        $this->assertContains($result['navigation_state'], [
            SmartRouteMatcher::STATE_ON_ASSIGNED,
            SmartRouteMatcher::STATE_JOINING,
            SmartRouteMatcher::STATE_SLIGHT_DEVIATION,
            SmartRouteMatcher::STATE_RECALCULATED,
        ]);
        $this->assertFalse($result['off_route_alert']);
    }

    public function test_progress_uses_snapped_position_not_zero_when_joining(): void
    {
        $route = $this->sampleRoute();
        $matcher = new SmartRouteMatcher();
        $path = [
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 22.0, 'lng' => 39.7],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ];

        $result = $matcher->evaluate($route, $path, 22.0, 39.70, 85, 20, [
            'last_dest_distance_m' => 300000,
        ]);

        $this->assertGreaterThan(5, $result['percentage_completed']);
    }

    private function sampleRoute(): RoutePlan
    {
        return new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'start_lat' => 21.4225,
            'start_lng' => 39.8262,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'expected_distance_km' => 400,
            'arrival_radius_meters' => 500,
        ]);
    }
}
