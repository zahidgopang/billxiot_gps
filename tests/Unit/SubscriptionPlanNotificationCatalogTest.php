<?php

namespace Tests\Unit;

use App\Models\VehicleEvent;
use App\Support\Billing\SubscriptionPlanNotificationCatalog;
use PHPUnit\Framework\TestCase;

class SubscriptionPlanNotificationCatalogTest extends TestCase
{
    private SubscriptionPlanNotificationCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new SubscriptionPlanNotificationCatalog;
    }

    public function test_canonical_key_maps_push_aliases(): void
    {
        $this->assertSame(
            VehicleEvent::TYPE_RUNNING,
            $this->catalog->canonicalKey('vehicle_started'),
        );
        $this->assertSame(
            VehicleEvent::TYPE_GEOFENCE_ENTER,
            $this->catalog->canonicalKey('geofence_enter'),
        );
        $this->assertSame(
            VehicleEvent::TYPE_MAINTENANCE,
            $this->catalog->canonicalKey('maintenance_due'),
        );
    }

    public function test_allows_standard_type_respects_plan_matrix(): void
    {
        $enabled = [VehicleEvent::TYPE_PANIC];

        $this->assertTrue($this->catalog->allowsStandardType($enabled, VehicleEvent::TYPE_PANIC));
        $this->assertFalse($this->catalog->allowsStandardType($enabled, VehicleEvent::TYPE_OVERSPEED));
        $this->assertFalse($this->catalog->allowsStandardType($enabled, 'vehicle_started'));
        $this->assertTrue($this->catalog->allowsStandardType([VehicleEvent::TYPE_RUNNING], 'vehicle_started'));
        $this->assertTrue($this->catalog->allowsStandardType([VehicleEvent::TYPE_MAINTENANCE], 'maintenance_due'));
    }

    public function test_route_notification_requires_route_id(): void
    {
        $this->assertTrue($this->catalog->allowsRouteNotification([1, 2], 1));
        $this->assertFalse($this->catalog->allowsRouteNotification([1, 2], 9));
        $this->assertTrue($this->catalog->allowsRouteNotification(null, 9));
        $this->assertFalse($this->catalog->allowsRouteNotification([1, 2], null));
        $this->assertFalse($this->catalog->allowsRouteNotification([], 1));
    }
}
