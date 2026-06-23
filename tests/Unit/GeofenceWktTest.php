<?php

namespace Tests\Unit;

use App\Support\Traccar\GeofenceWkt;
use PHPUnit\Framework\TestCase;

class GeofenceWktTest extends TestCase
{
    public function test_polygon_uses_traccar_lat_lng_order(): void
    {
        $coords = [
            [25.458577, 68.782766],
            [25.458509, 68.782660],
            [25.458400, 68.782500],
        ];

        $wkt = GeofenceWkt::fromLaravel('polygon', $coords, null, null);

        $this->assertStringStartsWith('POLYGON ((', $wkt);
        $this->assertStringContainsString('25.458577 68.782766', $wkt);
        $this->assertStringNotContainsString('68.782766 25.458577', $wkt);
    }

    public function test_circle_uses_traccar_lat_lng_order(): void
    {
        $wkt = GeofenceWkt::fromLaravel('circle', null, [25.75, 37.62], 100);

        $this->assertMatchesRegularExpression('/CIRCLE \(25\.75+ 37\.62+, 100/', $wkt);
    }
}
