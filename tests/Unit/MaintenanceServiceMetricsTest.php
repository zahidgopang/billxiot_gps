<?php

namespace Tests\Unit;

use App\Services\Tracking\DeviceOdometerService;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\MaintenanceService;
use App\Services\Authorization\TenantScopeService;
use Tests\TestCase;

class MaintenanceServiceMetricsTest extends TestCase
{
    public function test_odometer_left_and_expired_labels(): void
    {
        $service = $this->service();

        $ok = $service->computeMetrics([
            'odometer' => ['enabled' => true, 'interval' => 5000, 'last' => 80000],
        ], 82000);

        $this->assertSame('ok', $ok['status']);
        $this->assertSame(3000.0, $ok['odometer_left']);
        $this->assertSame('3000 km', $ok['odometer_left_label']);
        $this->assertNull($ok['odometer_exceeded_km']);

        $expired = $service->computeMetrics([
            'odometer' => ['enabled' => true, 'interval' => 5000, 'last' => 80000],
        ], 86000);

        $this->assertSame('overdue', $expired['status']);
        $this->assertSame(-1000.0, $expired['odometer_left']);
        $this->assertSame(1000.0, $expired['odometer_exceeded_km']);
        $this->assertStringContainsString('1000', (string) $expired['odometer_left_label']);
    }

    public function test_metrics_without_current_odometer_stay_ok(): void
    {
        $service = $this->service();

        $result = $service->computeMetrics([
            'odometer' => ['enabled' => true, 'interval' => 5000, 'last' => 80000],
        ], null);

        $this->assertSame('ok', $result['status']);
        $this->assertNull($result['odometer_left']);
        $this->assertNull($result['odometer_left_label']);
    }

    private function service(): MaintenanceService
    {
        return new MaintenanceService(
            $this->createMock(GlobalTrackingService::class),
            $this->createMock(DevicePositionLoader::class),
            $this->createMock(TenantScopeService::class),
            $this->createMock(DeviceOdometerService::class),
        );
    }
}
