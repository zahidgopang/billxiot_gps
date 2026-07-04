<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Tracking\NotificationPreferenceService;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarAttributes;
use Tests\TestCase;

class NotificationPreferenceServiceTest extends TestCase
{
    public function test_whatsapp_defaults_to_opted_out_when_no_maintenance_preference_is_stored(): void
    {
        $service = new NotificationPreferenceService;
        $user = new User([
            'attributes' => TraccarAttributes::encode([
                TraccarAppFields::KEY_NOTIFICATION_PREFERENCES => [
                    'overspeed' => [
                        'web' => true,
                        'push' => true,
                        'email' => true,
                        'whatsapp' => false,
                    ],
                ],
            ]),
        ]);

        $this->assertFalse($service->allowsWhatsApp($user, VehicleEvent::TYPE_MAINTENANCE));
        $this->assertTrue($service->allowsEmail($user, VehicleEvent::TYPE_MAINTENANCE));
        $this->assertTrue($service->allowsPush($user, VehicleEvent::TYPE_MAINTENANCE));
    }

    public function test_whatsapp_can_be_explicitly_enabled_for_maintenance(): void
    {
        $service = new NotificationPreferenceService;
        $user = new User([
            'attributes' => TraccarAttributes::encode([
                TraccarAppFields::KEY_NOTIFICATION_PREFERENCES => [
                    VehicleEvent::TYPE_MAINTENANCE => [
                        'web' => true,
                        'push' => true,
                        'email' => true,
                        'whatsapp' => true,
                    ],
                ],
            ]),
        ]);

        $this->assertTrue($service->allowsWhatsApp($user, 'maintenance_due'));
    }
}
