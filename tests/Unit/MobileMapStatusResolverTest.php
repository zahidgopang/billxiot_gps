<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Mobile\VehicleStatusSpec;
use Carbon\Carbon;
use Tests\TestCase;

class MobileMapStatusResolverTest extends TestCase
{
    private MobileMapStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-29 12:00:00', 'Asia/Karachi'));
        $this->resolver = new MobileMapStatusResolver;
    }

    public function test_offline_when_no_location(): void
    {
        $device = new Device(['status' => 'active']);

        $result = $this->resolver->resolve(null, $device);

        $this->assertSame('offline', $result['key']);
        $this->assertSame('offline', $result['connectivity_tier']);
    }

    /** QA scenario 1: running — ignition on, speed 15, 30 sec */
    public function test_running_when_ignition_on_and_speed_above_threshold(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 15, 'ignition' => true], 30);

        $result = $this->resolver->resolve($latest, $device);

        $this->assertSame('running', $result['key']);
        $this->assertSame('live', $result['connectivity_tier']);
    }

    /** QA scenario 2: stopped — ignition on, speed 0, 30 sec */
    public function test_stopped_when_ignition_on_and_speed_zero(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 0, 'ignition' => true], 30);

        $this->assertSame('stopped', $this->resolver->resolve($latest, $device)['key']);
    }

    /** QA scenario 3: parked — ignition off, speed 0, 30 sec */
    public function test_parked_when_ignition_off_and_speed_zero(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 0, 'ignition' => false], 30);

        $this->assertSame('parked', $this->resolver->resolve($latest, $device)['key']);
    }

    /** QA scenario 4: moving — ignition off, speed 15, 30 sec (not offline) */
    public function test_moving_when_ignition_off_and_speed_above_threshold(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 15, 'ignition' => false], 30);

        $result = $this->resolver->resolve($latest, $device);

        $this->assertSame('moving', $result['key']);
        $this->assertSame('live', $result['connectivity_tier']);
    }

    /** QA scenario 5: delayed — last update 5 min */
    public function test_delayed_when_last_update_five_minutes(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 15, 'ignition' => true], 300);

        $result = $this->resolver->resolve($latest, $device);

        $this->assertSame('delayed', $result['key']);
        $this->assertSame('delayed', $result['connectivity_tier']);
        $this->assertSame('running', $result['last_known_status_key']);
    }

    /** QA scenario 6: stale — last update 20 min */
    public function test_stale_when_last_update_twenty_minutes(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 89, 'ignition' => true], 1200);

        $result = $this->resolver->resolve($latest, $device);

        $this->assertSame('stale', $result['key']);
        $this->assertSame('stale', $result['connectivity_tier']);
        $this->assertSame('running', $result['last_known_status_key']);
    }

    /** QA scenario 7: offline — last update 45 min (not because of ignition or speed) */
    public function test_offline_only_when_last_update_exceeds_thirty_minutes(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 0, 'ignition' => false], 2700);

        $result = $this->resolver->resolve($latest, $device);

        $this->assertSame('offline', $result['key']);
        $this->assertSame('offline', $result['connectivity_tier']);
        $this->assertSame('parked', $result['last_known_status_key']);
    }

    public function test_parked_with_high_last_known_speed_is_offline_not_moving_display(): void
    {
        $device = new Device(['status' => 'active']);
        $latest = $this->location(['speed' => 89, 'ignition' => true], 2700);

        $result = $this->resolver->resolve($latest, $device);

        $this->assertSame('offline', $result['key']);
        $this->assertSame('running', $result['last_known_status_key']);
    }

    public function test_vehicle_status_spec_motion_threshold_is_five_kmh(): void
    {
        $this->assertSame('stopped', VehicleStatusSpec::motionKey(5, true));
        $this->assertSame('running', VehicleStatusSpec::motionKey(5.1, true));
        $this->assertSame('parked', VehicleStatusSpec::motionKey(5, false));
        $this->assertSame('moving', VehicleStatusSpec::motionKey(6, false));
    }

    private function location(array $overrides, int $secondsAgo): DeviceLocation
    {
        return new DeviceLocation(array_merge([
            'lat' => 25.46,
            'lng' => 68.78,
            'recorded_at' => Carbon::now()->subSeconds($secondsAgo),
        ], $overrides));
    }
}
