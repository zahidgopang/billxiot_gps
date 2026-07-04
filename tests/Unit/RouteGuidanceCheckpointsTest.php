<?php

namespace Tests\Unit;

use App\Models\RoutePlan;
use App\Services\Routes\RouteGuidanceService;
use App\Support\Geo\GeoLocalityResolver;
use Mockery;
use Tests\TestCase;

class RouteGuidanceCheckpointsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_checkpoint_rows_from_single_leg_route(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'start_lat' => 21.4225,
            'start_lng' => 39.8262,
            'destination_lat' => 24.4672,
            'destination_lng' => 39.6111,
            'guided_distance_km' => 420,
            'guided_duration_minutes' => 240,
        ]);

        $locality = Mockery::mock(GeoLocalityResolver::class);
        $locality->shouldReceive('sanitizeLabel')->andReturnUsing(fn (?string $v) => $v);
        $locality->shouldReceive('reverseGeocode')->andReturn('Rabigh');

        $service = new RouteGuidanceService($locality);

        $vertices = [];
        for ($i = 0; $i < 200; $i++) {
            $vertices[] = [
                'lat' => 21.4225 + ($i * 0.015),
                'lng' => 39.8262 + ($i * 0.002),
            ];
        }

        $rows = $service->buildCheckpointRows($route, [
            'vertices' => $vertices,
            'legs' => [[
                'distance' => ['value' => 420000],
                'duration' => ['value' => 14400],
            ]],
        ]);

        $this->assertNotEmpty($rows);
        $this->assertSame('Makkah', $rows[0]['from_location']);
        $this->assertSame('Madinah', $rows[array_key_last($rows)]['to_location']);
    }
}
