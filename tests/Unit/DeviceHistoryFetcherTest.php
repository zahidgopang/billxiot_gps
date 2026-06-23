<?php

namespace Tests\Unit;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Services\Tracking\DeviceHistoryFetcher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class DeviceHistoryFetcherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_empty_last_24_hours_falls_back_to_last_known_activity(): void
    {
        $device = new Device(['id' => 42]);
        $latestAt = Carbon::parse('2026-05-20 14:00:00');
        $fallbackLocations = collect([
            $this->location(1, 25.0, 55.0, $latestAt->copy()->subHour()),
            $this->location(2, 25.1, 55.1, $latestAt),
        ]);

        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('historyForDevice')
            ->once()
            ->with($device, Mockery::type(Carbon::class), null, 'asc')
            ->andReturn(collect());

        $positions->shouldReceive('latestForDevice')
            ->once()
            ->with($device)
            ->andReturn($this->location(99, 25.1, 55.1, $latestAt));

        $positions->shouldReceive('historyForDevice')
            ->once()
            ->with($device, Mockery::type(Carbon::class), Mockery::type(Carbon::class), 'asc')
            ->andReturn($fallbackLocations);

        $fetcher = new DeviceHistoryFetcher($positions);
        $from = now()->subHours(24);

        $result = $fetcher->fetch($device, $from, null, false);

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('last_known_activity', $result['fallback_reason']);
        $this->assertCount(2, $result['locations']);
    }

    public function test_explicit_empty_range_falls_back_with_selected_period_reason(): void
    {
        $device = new Device(['id' => 7]);
        $latestAt = Carbon::parse('2026-05-18 09:00:00');
        $fallbackLocations = collect([
            $this->location(10, 24.0, 54.0, $latestAt),
        ]);

        $positions = Mockery::mock(PositionReaderInterface::class);
        $positions->shouldReceive('historyForDevice')
            ->once()
            ->with($device, Mockery::type(Carbon::class), Mockery::type(Carbon::class), 'asc')
            ->andReturn(collect());

        $positions->shouldReceive('latestForDevice')
            ->once()
            ->with($device)
            ->andReturn($this->location(10, 24.0, 54.0, $latestAt));

        $positions->shouldReceive('historyForDevice')
            ->once()
            ->with($device, Mockery::type(Carbon::class), Mockery::type(Carbon::class), 'asc')
            ->andReturn($fallbackLocations);

        $fetcher = new DeviceHistoryFetcher($positions);
        $from = Carbon::parse('2026-05-01 00:00:00');
        $to = Carbon::parse('2026-05-02 23:59:59');

        $result = $fetcher->fetch($device, $from, $to, true);

        $this->assertTrue($result['used_fallback']);
        $this->assertSame('selected_period_empty', $result['fallback_reason']);
        $this->assertCount(1, $result['locations']);
    }

    private function location(int $id, float $lat, float $lng, Carbon $recordedAt): DeviceLocation
    {
        $location = new DeviceLocation([
            'id' => $id,
            'device_id' => 42,
            'lat' => $lat,
            'lng' => $lng,
            'speed' => 10,
            'heading' => 90,
            'recorded_at' => $recordedAt,
        ]);
        $location->id = $id;

        return $location;
    }
}
