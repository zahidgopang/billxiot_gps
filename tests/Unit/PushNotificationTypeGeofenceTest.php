<?php

namespace Tests\Unit;

use App\Support\Push\PushNotificationType;
use Tests\TestCase;

class PushNotificationTypeGeofenceTest extends TestCase
{
    public function test_geofence_enter_and_exit_always_deliver_even_when_major_status_only(): void
    {
        config(['tracking.push_major_status_only' => true]);

        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_ENTER));
        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_EXIT));
        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::VEHICLE_STARTED));
        $this->assertFalse(PushNotificationType::deliverViaPush(PushNotificationType::OVERSPEED));
        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::PANIC));
    }

    public function test_geofence_still_delivers_when_major_status_only_false(): void
    {
        config(['tracking.push_major_status_only' => false]);

        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_ENTER));
        $this->assertTrue(PushNotificationType::deliverViaPush(PushNotificationType::GEOFENCE_EXIT));
    }
}
