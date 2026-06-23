<?php

use App\Models\Device;
use App\Services\Traccar\TraccarDeviceAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('device.{deviceId}', function ($user, $deviceId) {
    if (! $user) {
        return false;
    }

    $device = Device::query()->find($deviceId);

    if (! $device) {
        return false;
    }

    return app(TraccarDeviceAccessService::class)->userCanAccessDevice($user, $device);
});
