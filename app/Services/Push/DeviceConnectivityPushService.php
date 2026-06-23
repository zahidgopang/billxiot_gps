<?php

namespace App\Services\Push;

use App\Models\Device;
use App\Services\SmartFleetAlertService;
use App\Support\Push\PushNotificationType;
use Carbon\Carbon;

class DeviceConnectivityPushService
{
    public function __construct(
        private PushNotificationDispatcher $dispatcher,
        private SmartFleetAlertService $smartAlerts,
    ) {}

    public function onPositionReceived(Device $device): void
    {
        $cacheKey = $this->cacheKey($device->id);
        $wasOnline = (bool) cache()->get($cacheKey, false);

        if (! $wasOnline) {
            $this->dispatcher->forConnectivity(
                $device,
                PushNotificationType::DEVICE_ONLINE,
                sprintf('%s is back online and reporting GPS.', $device->notificationDisplayName()),
            );
        }

        cache()->put($cacheKey, true, now()->addHours(24));
    }

    public function checkDevice(Device $device): void
    {
        $this->smartAlerts->checkConnectivity($device);
    }

    private function cacheKey(int $deviceId): string
    {
        return "device.{$deviceId}.push_connectivity";
    }
}
