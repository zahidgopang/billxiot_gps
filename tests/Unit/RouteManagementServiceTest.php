<?php

namespace Tests\Unit;

use App\Models\RouteCheckpoint;
use App\Models\RoutePlan;
use App\Services\Routes\RouteGuidanceService;
use App\Services\Routes\RouteManagementService;
use App\Support\Geo\PolylineEncoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RouteManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_preserves_user_checkpoints_when_syncing_polyline(): void
    {
        $route = RoutePlan::query()->create([
            'name' => 'Makkah to Madinah',
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'start_lat' => 21.4241,
            'start_lng' => 39.8173,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'expected_distance_km' => 420,
            'expected_duration_minutes' => 300,
            'arrival_radius_meters' => 500,
            'show_polyline' => true,
            'show_progress_bar' => true,
            'status' => RoutePlan::STATUS_ACTIVE,
        ]);

        $checkpoints = [
            [
                'sequence' => 1,
                'from_location' => 'Makkah',
                'to_location' => 'Jeddah',
                'from_lat' => 21.4241,
                'from_lng' => 39.8173,
                'to_lat' => 21.5433,
                'to_lng' => 39.1728,
                'distance_km' => 75,
                'expected_duration_minutes' => 60,
            ],
        ];

        $encoded = PolylineEncoder::encode([
            ['lat' => 21.4241, 'lng' => 39.8173],
            ['lat' => 21.5433, 'lng' => 39.1728],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ]);

        $guidance = Mockery::mock(RouteGuidanceService::class);
        $guidance->shouldReceive('fetchFromGoogle')
            ->once()
            ->with(Mockery::on(fn (RoutePlan $r) => $r->id === $route->id))
            ->andReturn([
                'encoded_polyline' => $encoded,
                'vertices' => PolylineEncoder::decode($encoded),
                'distance_km' => 420.0,
                'duration_minutes' => 300,
                'legs' => [],
            ]);
        $guidance->shouldReceive('persistGuidanceResult')->once();
        $guidance->shouldReceive('forgetCache')->zeroOrMoreTimes();
        $guidance->shouldNotReceive('buildCheckpointRows');

        $service = new RouteManagementService($guidance);
        $service->update($route, [
            'name' => $route->name,
            'start_city' => $route->start_city,
            'destination_city' => $route->destination_city,
            'start_lat' => $route->start_lat,
            'start_lng' => $route->start_lng,
            'destination_lat' => $route->destination_lat,
            'destination_lng' => $route->destination_lng,
            'expected_distance_km' => $route->expected_distance_km,
            'expected_duration_minutes' => $route->expected_duration_minutes,
            'arrival_radius_meters' => $route->arrival_radius_meters,
            'show_polyline' => true,
            'show_progress_bar' => true,
            'status' => RoutePlan::STATUS_ACTIVE,
        ], $checkpoints);

        $saved = RouteCheckpoint::query()->where('route_id', $route->id)->orderBy('sequence')->get();
        $this->assertCount(1, $saved);
        $this->assertSame('Jeddah', $saved->first()->to_location);
        $this->assertEqualsWithDelta(21.5433, (float) $saved->first()->to_lat, 0.0001);
    }
}
