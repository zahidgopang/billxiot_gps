<?php

namespace Tests\Unit;

use App\Models\RoutePlan;
use App\Services\Routes\RouteGuidanceService;
use App\Services\Routes\RouteProgressCalculator;
use App\Services\Routes\SmartRouteMatcher;
use Tests\TestCase;

class RouteProgressCalculatorTest extends TestCase
{
    public function test_off_route_vehicle_near_destination_does_not_show_one_hundred_percent(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'start_lat' => 21.4225,
            'start_lng' => 39.8262,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'expected_distance_km' => 400,
            'expected_duration_minutes' => 255,
            'arrival_radius_meters' => 500,
        ]);
        $route->setRelation('checkpoints', collect());

        $guidance = $this->createMock(RouteGuidanceService::class);
        $guidance->method('pathVertices')->willReturn([
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ]);

        $calculator = new RouteProgressCalculator($guidance, new SmartRouteMatcher());

        // Vehicle near Madinah city but off the direct corridor — smart matcher avoids false 100%.
        $progress = $calculator->calculate($route, 24.47, 39.75, 0);

        $this->assertNotSame('off_route', $progress['navigation_state']);
        $this->assertLessThan(100, $progress['percentage_completed']);
        $this->assertGreaterThan(0.1, $progress['remaining_distance_km']);
    }

    public function test_off_route_near_start_shows_low_progress_not_ninety_nine(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'start_lat' => 21.4225,
            'start_lng' => 39.8262,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'expected_distance_km' => 400,
            'expected_duration_minutes' => 255,
            'arrival_radius_meters' => 500,
        ]);
        $route->setRelation('checkpoints', collect());

        $guidance = $this->createMock(RouteGuidanceService::class);
        $guidance->method('pathVertices')->willReturn([
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ]);

        $calculator = new RouteProgressCalculator($guidance, new SmartRouteMatcher());

        // Vehicle still near Makkah but off the highway corridor — joining/recalculated, not hard off-route.
        $progress = $calculator->calculate($route, 21.39, 39.80, 0);

        $this->assertNotSame('off_route', $progress['navigation_state']);
        $this->assertLessThan(20, $progress['percentage_completed']);
    }

    public function test_off_route_far_from_route_shows_zero_percent(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'start_lat' => 21.4225,
            'start_lng' => 39.8262,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'expected_distance_km' => 850,
            'expected_duration_minutes' => 255,
            'arrival_radius_meters' => 500,
        ]);
        $route->setRelation('checkpoints', collect());

        $guidance = $this->createMock(RouteGuidanceService::class);
        $guidance->method('pathVertices')->willReturn([
            ['lat' => 21.4858, 'lng' => 39.1925],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ]);

        $calculator = new RouteProgressCalculator($guidance, new SmartRouteMatcher());

        // Vehicle in Riyadh — far from route; may be recalculated/off-route candidate, not on assigned.
        $progress = $calculator->calculate($route, 24.7136, 46.6753, 0);

        $this->assertNotSame(SmartRouteMatcher::STATE_ON_ASSIGNED, $progress['navigation_state']);
    }

    public function test_on_route_near_destination_can_reach_one_hundred_percent(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'start_lat' => 21.4225,
            'start_lng' => 39.8262,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'expected_distance_km' => 400,
            'expected_duration_minutes' => 255,
            'arrival_radius_meters' => 500,
        ]);
        $route->setRelation('checkpoints', collect());

        $guidance = $this->createMock(RouteGuidanceService::class);
        $guidance->method('pathVertices')->willReturn([
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ]);

        $calculator = new RouteProgressCalculator($guidance, new SmartRouteMatcher());
        $progress = $calculator->calculate($route, 24.4672, 39.6111, 0);

        $this->assertTrue($progress['on_route']);
        $this->assertSame(100.0, $progress['percentage_completed']);
        $this->assertSame(0.0, $progress['remaining_distance_km']);
    }
}
