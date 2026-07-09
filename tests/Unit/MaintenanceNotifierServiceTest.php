<?php

namespace Tests\Unit;

use App\Contracts\Tracking\EventWriterInterface;
use App\Services\Push\PushNotificationDispatcher;
use App\Services\Tracking\DeviceOdometerService;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\MaintenanceNotifierService;
use App\Services\Tracking\TrackingSettingsService;
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

    public function test_odometer_cycle_stays_on_first_due_until_manual_complete(): void
    {
        $service = $this->service();

        // Overdue by multiple intervals still reports the original next-due threshold
        // until the owner marks the service completed (advances last).
        $this->assertSame(
            ['threshold' => 500.0],
            $service->dueOdometerCycle(0, 500, 1200),
        );

        $this->assertSame(
            ['threshold' => 1500.0],
            $service->dueOdometerCycle(1000, 500, 1500),
        );
    }

    public function test_day_cycle_returns_first_due_date_when_overdue(): void
    {
        $service = $this->service();

        $due = $service->effectiveDueDate(
            '2026-07-01',
            10,
            Carbon::parse('2026-07-25'),
        );

        $this->assertSame('2026-07-11', $due?->format('Y-m-d'));
    }

    private function service(): MaintenanceNotifierService
    {
        return new MaintenanceNotifierService(
            $this->createMock(EventWriterInterface::class),
            $this->createMock(PushNotificationDispatcher::class),
            $this->createMock(DevicePositionLoader::class),
            $this->createMock(DeviceOdometerService::class),
            $this->createMock(TrackingSettingsService::class),
        );
    }
}
