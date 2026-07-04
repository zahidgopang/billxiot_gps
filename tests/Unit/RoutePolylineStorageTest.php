<?php

namespace Tests\Unit;

use App\Models\RoutePlan;
use App\Support\Geo\PolylineDecoder;
use App\Support\Geo\PolylineEncoder;
use Tests\TestCase;

class RoutePolylineStorageTest extends TestCase
{
    public function test_polyline_encoder_roundtrip(): void
    {
        $points = [
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 21.5433, 'lng' => 39.1728],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ];

        $encoded = PolylineEncoder::encode($points);
        $decoded = PolylineDecoder::decode($encoded);

        $this->assertNotSame('', $encoded);
        $this->assertCount(3, $decoded);
        $this->assertEqualsWithDelta(21.4225, $decoded[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(39.8262, $decoded[0]['lng'], 0.0001);
    }

    public function test_route_plan_stored_path_vertices(): void
    {
        $points = [
            ['lat' => 21.4225, 'lng' => 39.8262],
            ['lat' => 24.4672, 'lng' => 39.6111],
        ];

        $route = new RoutePlan([
            'show_polyline' => true,
            'encoded_polyline' => PolylineEncoder::encode($points),
        ]);

        $this->assertTrue($route->hasStoredPolyline());
        $vertices = $route->storedPathVertices();
        $this->assertCount(2, $vertices);
    }
}
