<?php

namespace Tests\Unit;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Tracking\StatusDurationResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StatusDurationResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00:00', 'Asia/Riyadh'));
    }

    public function test_offline_duration_is_time_since_last_fix_not_motion_history(): void
    {
        $device = new Device(['id' => 1, 'status' => 'active']);
        $recordedAt = now()->subHours(128)->subMinutes(10)->subSeconds(59);
        $latest = new DeviceLocation([
            'speed' => 0,
            'ignition' => false,
            'recorded_at' => $recordedAt,
        ]);

        $map = [
            'key' => 'offline',
            'label' => 'Offline',
            'connectivity_tier' => 'offline',
            'last_known_status' => 'Parked',
            'last_known_status_key' => 'parked',
        ];

        $positions = $this->createMock(PositionReaderInterface::class);
        $positions->expects($this->never())->method('historyForDevice');

        $resolver = new StatusDurationResolver($positions, new MobileMapStatusResolver);
        $result = $resolver->resolve($device, $latest, $map);

        $this->assertSame($recordedAt->timestamp, $result['since']?->timestamp);
        $this->assertSame(
            (int) $recordedAt->diffInSeconds(now()),
            $result['seconds'],
        );
    }

    public function test_live_duration_uses_motion_history(): void
    {
        $device = new Device(['id' => 2, 'status' => 'active']);
        $recordedAt = now()->subMinutes(5);
        $parkedSince = now()->subHours(2);
        $latest = new DeviceLocation([
            'speed' => 0,
            'ignition' => false,
            'recorded_at' => $recordedAt,
        ]);

        $map = [
            'key' => 'parked',
            'label' => 'Parked',
            'connectivity_tier' => 'live',
            'last_known_status' => 'Parked',
            'last_known_status_key' => 'parked',
        ];

        $olderRunning = new DeviceLocation([
            'speed' => 20,
            'ignition' => true,
            'recorded_at' => $parkedSince->copy()->subHour(),
        ]);
        $parkedPoint = new DeviceLocation([
            'speed' => 0,
            'ignition' => false,
            'recorded_at' => $parkedSince,
        ]);

        $positions = $this->createMock(PositionReaderInterface::class);
        $positions->method('historyForDevice')->willReturn(new Collection([
            $latest,
            $parkedPoint,
            $olderRunning,
        ]));

        $resolver = new StatusDurationResolver($positions, new MobileMapStatusResolver);
        $result = $resolver->resolve($device, $latest, $map);

        $this->assertSame($parkedSince->timestamp, $result['since']?->timestamp);
        $this->assertSame(
            (int) $parkedSince->diffInSeconds(now()),
            $result['seconds'],
        );
    }
}
