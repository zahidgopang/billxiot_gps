<?php

namespace Tests\Unit;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Tracking\DeviceOdometerService;
use Mockery;
use Tests\TestCase;

class DeviceOdometerBaselineTest extends TestCase
{
    public function test_set_baseline_uses_position_reader_not_device_locations_table(): void
    {
        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('latestForDevice')
            ->once()
            ->andReturn(null);

        $device = Mockery::mock(Device::class)->makePartial();
        $device->shouldReceive('relationLoaded')->with('latestLocation')->andReturn(false);
        $device->shouldReceive('setRelation')->once()->with('latestLocation', null);
        $device->shouldReceive('getTraccarAttributesJson')->andReturn('{}');
        $device->shouldReceive('patchTraccarAppAttributes')->once()->with(Mockery::on(function (array $patch) {
            return isset($patch['odometer_base_km'])
                && (float) $patch['odometer_base_km'] === 85000.0
                && (int) ($patch['odometer_accum_version'] ?? 0) === DeviceOdometerService::ACCUM_VERSION;
        }));
        $device->shouldReceive('save')->once()->andReturn(true);
        $device->id = 99;

        $service = new DeviceOdometerService($positions);
        $service->setBaseline($device, 85000);

        $this->addToAssertionCount(1);
    }

    public function test_set_baseline_stores_last_gps_point_from_position_reader(): void
    {
        $loc = new DeviceLocation([
            'lat' => 24.7,
            'lng' => 46.6,
            'recorded_at' => now()->subHours(6),
        ]);

        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('latestForDevice')->once()->andReturn($loc);

        $device = Mockery::mock(Device::class)->makePartial();
        $device->shouldReceive('relationLoaded')->with('latestLocation')->andReturn(false);
        $device->shouldReceive('setRelation')->once()->with('latestLocation', $loc);
        $device->shouldReceive('getTraccarAttributesJson')->andReturn('{}');
        $device->shouldReceive('patchTraccarAppAttributes')->once()->with(Mockery::on(function (array $patch) use ($loc) {
            // Lat/lng may come from latest fix, but last_accum_at must NOT backdate to that old fix.
            $lastAt = isset($patch['odometer_last_accum_at'])
                ? \Carbon\Carbon::parse($patch['odometer_last_accum_at'])
                : null;

            return (float) ($patch['odometer_last_accum_lat'] ?? 0) === 24.7
                && (float) ($patch['odometer_last_accum_lng'] ?? 0) === 46.6
                && $lastAt !== null
                && $lastAt->greaterThan($loc->recorded_at)
                && (int) ($patch['odometer_accum_version'] ?? 0) === DeviceOdometerService::ACCUM_VERSION;
        }));
        $device->shouldReceive('save')->once()->andReturn(true);
        $device->id = 100;

        $service = new DeviceOdometerService($positions);
        $service->setBaseline($device, 12000);
    }

    public function test_should_not_count_stationary_gps_jitter(): void
    {
        $service = new DeviceOdometerService(Mockery::mock(PositionReaderInterface::class));
        $method = new \ReflectionMethod(DeviceOdometerService::class, 'shouldCountSegment');
        $method->setAccessible(true);

        $from = now()->subSeconds(30);
        $to = now();

        // ~5 m hop while "stopped" — must not inflate odometer.
        $this->assertFalse($method->invoke(
            $service,
            0.005,
            $from,
            $to,
            0.2,
            true,
        ));

        // Clear moving hop at ~40 km/h over 30 s ≈ 0.33 km.
        $this->assertTrue($method->invoke(
            $service,
            0.33,
            $from,
            $to,
            40.0,
            true,
        ));

        // Teleport: 12 km in 30 s.
        $this->assertFalse($method->invoke(
            $service,
            12.0,
            $from,
            $to,
            null,
            true,
        ));
    }

    public function test_display_km_falls_back_to_traccar_reported_odometer(): void
    {
        $loc = new DeviceLocation([
            'lat' => 24.7,
            'lng' => 46.6,
            'odometer' => 12500000, // meters
        ]);

        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('latestForDevice')->once()->andReturn($loc);

        $device = Mockery::mock(Device::class)->makePartial();
        $device->shouldReceive('relationLoaded')->with('latestLocation')->andReturn(false);
        $device->shouldReceive('setRelation')->once()->with('latestLocation', $loc);
        $device->shouldReceive('getTraccarAttributesJson')->andReturn('{}');
        $device->id = 101;

        $service = new DeviceOdometerService($positions);
        $km = $service->displayKm($device);

        $this->assertSame(12500.0, $km);
    }
}
