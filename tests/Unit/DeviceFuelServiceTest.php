<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Tracking\DeviceFuelService;
use Tests\TestCase;

class DeviceFuelServiceTest extends TestCase
{
    public function test_estimated_liters_from_rate_and_distance(): void
    {
        $service = new DeviceFuelService;

        $this->assertSame(12.5, $service->estimatedLiters(100, 12.5));
        $this->assertSame(6.25, $service->estimatedLiters(50, 12.5));
        $this->assertNull($service->estimatedLiters(50, null));
    }

    public function test_efficiency_units(): void
    {
        $service = new DeviceFuelService;

        $this->assertSame(12.5, $service->efficiencyValue(100, 12.5, DeviceFuelService::UNIT_L_PER_100KM));
        $this->assertSame(8.0, $service->efficiencyValue(100, 12.5, DeviceFuelService::UNIT_KM_PER_L));
    }

    public function test_sensor_consumption_ignores_refills(): void
    {
        $service = new DeviceFuelService;
        $device = new Device;
        $device->forceFill(['id' => 1]);

        $points = [
            $this->point(80),
            $this->point(75),
            $this->point(70),
            $this->point(95), // refill
            $this->point(90),
        ];

        $result = $service->sensorConsumedLiters($points, $device);

        $this->assertTrue($result['has_sensor']);
        $this->assertSame(1, $result['refill_count']);
        $this->assertSame(15.0, $result['liters']); // 5 + 5 + 5
    }

    private function point(float $fuel): DeviceLocation
    {
        $location = new DeviceLocation([
            'lat' => 21.4,
            'lng' => 39.8,
            'fuel' => $fuel,
        ]);

        return $location;
    }
}
