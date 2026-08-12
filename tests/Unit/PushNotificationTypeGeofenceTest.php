<?php

namespace Tests\Unit;

use App\Support\Push\PushNotificationType;
use Tests\TestCase;

class PushNotificationTypeGeofenceTest extends TestCase
{
    public function test_geofence_enter_and_exit_deliver_when_major_status_only(): void
    {
        config(['tracking.push_major_status_only' => true]);

        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_ENTER));
        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_EXIT));
        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::VEHICLE_STARTED));
        $this->assertFalse(PushNotificationType::deliverViaPush(PushNotificationType::OVERSPEED));
        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::PANIC));
    }
}
