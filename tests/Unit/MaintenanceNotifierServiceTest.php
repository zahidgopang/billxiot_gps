<?php

namespace Tests\Unit;

use App\Contracts\Tracking\EventWriterInterface;
use App\Services\Push\PushNotificationDispatcher;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\MaintenanceNotifierService;
use Carbon\Carbon;
use Tests\TestCase;

class MaintenanceNotifierServiceTest extends TestCase
{
    public function test_odometer_cycle_is_due_only_when_interval_is_crossed(): void
    {
        $service = $this->service();

        $this->assertNull($service->dueOdometerCycle(0, 500, 499.9));

        $this->assertSame(
            ['threshold' => 500.0],
            $service->dueOdometerCycle(0, 500, 500),
        );
    }

    public function test_odometer_cycle_uses_latest_crossed_interval_for_repeat_reminders(): void
    {
        $service = $this->service();

        $this->assertSame(
            ['threshold' => 1000.0],
            $service->dueOdometerCycle(0, 500, 1200),
        );

        $this->assertSame(
            ['threshold' => 1500.0],
            $service->dueOdometerCycle(1000, 500, 1500),
        );
    }

    public function test_day_cycle_uses_latest_crossed_due_date(): void
    {
        $service = $this->service();

        $due = $service->effectiveDueDate(
            '2026-07-01',
            10,
            Carbon::parse('2026-07-25'),
        );

        $this->assertSame('2026-07-21', $due?->format('Y-m-d'));
    }

    private function service(): MaintenanceNotifierService
    {
        return new MaintenanceNotifierService(
            $this->createMock(EventWriterInterface::class),
            $this->createMock(PushNotificationDispatcher::class),
            $this->createMock(DevicePositionLoader::class),
        );
    }
}
