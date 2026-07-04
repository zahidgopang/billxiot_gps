<?php

namespace Tests\Unit;

use App\Models\RoutePlan;
use App\Models\TripLog;
use App\Services\Routes\RouteGuidanceService;
use App\Services\Routes\SmartRouteMatcher;
use App\Services\Routes\TripNavigationService;
use Mockery;
use Tests\TestCase;

class TripNavigationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_join_polyline_uses_origin_to_vehicle_when_far_from_corridor(): void
    {
        $route = new RoutePlan([
            'start_lat' => 21.4241,
            'start_lng' => 39.8173,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'show_polyline' => true,
        ]);
        $route->id = 1;

        $vehicleLat = 24.7136;
        $vehicleLng = 46.6753;

        $guidance = Mockery::mock(RouteGuidanceService::class);
        $guidance->shouldReceive('pathVertices')->andReturn([
            ['lat' => 21.4241, 'lng' => 39.8173],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ]);
        $guidance->shouldReceive('fetchPointToPointRoute')
            ->once()
            ->with(21.4241, 39.8173, $vehicleLat, $vehicleLng)
            ->andReturn([
                ['lat' => 21.4241, 'lng' => 39.8173],
                ['lat' => 23.0, 'lng' => 42.0],
                ['lat' => $vehicleLat, 'lng' => $vehicleLng],
            ]);

        $service = new TripNavigationService($guidance, new SmartRouteMatcher());
        $path = $service->joinPolyline($route, $vehicleLat, $vehicleLng);

        $this->assertGreaterThanOrEqual(2, count($path));
        $this->assertEqualsWithDelta(21.4241, $path[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta($vehicleLat, $path[count($path) - 1]['lat'], 0.0001);
    }

    public function test_should_show_join_polyline_when_vehicle_far_from_route_start(): void
    {
        $route = new RoutePlan([
            'start_lat' => 21.4241,
            'start_lng' => 39.8173,
        ]);
        $trip = new TripLog([
            'status' => TripLog::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'navigation_context' => ['last_state' => SmartRouteMatcher::STATE_JOINING],
        ]);

        $guidance = Mockery::mock(RouteGuidanceService::class);
        $service = new TripNavigationService($guidance, new SmartRouteMatcher());

        $this->assertTrue($service->shouldShowJoinPolyline($route, $trip, 24.7136, 46.6753));
        $this->assertFalse($service->shouldShowJoinPolyline($route, $trip, 21.4245, 39.8175));
    }
}
