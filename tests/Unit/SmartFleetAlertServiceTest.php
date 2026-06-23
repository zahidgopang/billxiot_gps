<?php

namespace Tests\Unit;

use App\Contracts\Tracking\EventWriterInterface;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\VehicleEvent;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Push\PushNotificationDispatcher;
use App\Services\SmartFleetAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SmartFleetAlertServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delayed_alert_when_no_data_between_ten_and_thirty_minutes(): void
    {
        Cache::flush();

        $device = new Device(['id' => 1, 'status' => 'active', 'vehicle_name' => 'Truck 1']);
        $location = $this->location(minutesAgo: 15, speed: 0, ignition: false);

        $events = Mockery::mock(EventWriterInterface::class);
        $events->shouldReceive('record')
            ->once()
            ->withArgs(fn ($d, $type) => $d === $device && $type === VehicleEvent::TYPE_DELAYED)
            ->andReturn($this->vehicleEvent(VehicleEvent::TYPE_DELAYED));

        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('latestForDevice')->with($device)->andReturn($location);

        $push = Mockery::mock(PushNotificationDispatcher::class);
        $push->shouldReceive('forSmartAlert')->once();

        $service = new SmartFleetAlertService(
            $events,
            $positions,
            new MobileMapStatusResolver,
            $push,
        );

        $service->checkConnectivity($device);

        $this->assertTrue(Cache::has('device.1.alert.delayed'));
    }

    public function test_tampering_alert_when_delayed_after_movement(): void
    {
        Cache::flush();

        $device = new Device(['id' => 4, 'status' => 'active', 'vehicle_name' => 'Truck 4']);
        $location = $this->location(minutesAgo: 15, speed: 40, ignition: true);

        $events = Mockery::mock(EventWriterInterface::class);
        $events->shouldReceive('record')->twice()->andReturnUsing(
            fn ($d, $type) => $this->vehicleEvent($type)
        );

        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('latestForDevice')->with($device)->andReturn($location);

        $push = Mockery::mock(PushNotificationDispatcher::class);
        $push->shouldReceive('forSmartAlert')->twice();

        $service = new SmartFleetAlertService(
            $events,
            $positions,
            new MobileMapStatusResolver,
            $push,
        );

        $service->checkConnectivity($device);

        $this->assertTrue(Cache::has('device.4.alert.tampering'));
    }

    public function test_comm_lost_moving_when_offline_after_movement(): void
    {
        Cache::flush();

        $device = new Device(['id' => 2, 'status' => 'active', 'vehicle_name' => 'Van 2']);
        $location = $this->location(minutesAgo: 45, speed: 40, ignition: true);

        $events = Mockery::mock(EventWriterInterface::class);
        $events->shouldReceive('record')
            ->once()
            ->withArgs(fn ($d, $type) => $type === VehicleEvent::TYPE_COMM_LOST_MOVING)
            ->andReturn($this->vehicleEvent(VehicleEvent::TYPE_COMM_LOST_MOVING));

        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('latestForDevice')->with($device)->andReturn($location);

        $push = Mockery::mock(PushNotificationDispatcher::class);
        $push->shouldReceive('forSmartAlert')->once();

        $service = new SmartFleetAlertService(
            $events,
            $positions,
            new MobileMapStatusResolver,
            $push,
        );

        $service->checkConnectivity($device);

        $this->assertTrue(Cache::has('device.2.alert.comm_moving'));
    }

    public function test_clears_episode_cache_when_fresh_data_received(): void
    {
        Cache::put('device.3.alert.delayed', true, now()->addHour());

        $device = new Device(['id' => 3, 'status' => 'active', 'vehicle_name' => 'Car 3']);
        $location = $this->location(minutesAgo: 1, speed: 0, ignition: false);

        $events = Mockery::mock(EventWriterInterface::class);
        $events->shouldNotReceive('record');

        $positions = Mockery::mock(PositionReaderInterface::class);

        $push = Mockery::mock(PushNotificationDispatcher::class);
        $push->shouldNotReceive('forSmartAlert');

        $service = new SmartFleetAlertService(
            $events,
            $positions,
            new MobileMapStatusResolver,
            $push,
        );

        $service->onPositionReceived($device, $location);

        $this->assertFalse(Cache::has('device.3.alert.delayed'));
    }

    private function location(int $minutesAgo, float $speed, bool $ignition): DeviceLocation
    {
        return new DeviceLocation([
            'lat' => 24.86,
            'lng' => 67.00,
            'speed' => $speed,
            'ignition' => $ignition,
            'recorded_at' => Carbon::now()->subMinutes($minutesAgo),
        ]);
    }

    private function vehicleEvent(string $type): VehicleEvent
    {
        return new VehicleEvent([
            'id' => 99,
            'type' => $type,
            'title' => 'Test',
            'message' => 'Test message',
            'occurred_at' => now(),
        ]);
    }
}
