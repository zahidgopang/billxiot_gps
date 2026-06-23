<?php

namespace App\Http\Concerns;

use App\Models\Device;
use App\Models\User;
use App\Services\DeviceAccessService;
use Illuminate\Http\Exceptions\HttpResponseException;

trait ResolvesMobileDevice
{
    protected function findMobileDevice(User $user, int|string $id): Device
    {
        $device = $user->trackerDevicesQuery()
            ->with(['subscription.clientInvoice'])
            ->whereKey((int) $id)
            ->firstOrFail();

        $check = app(DeviceAccessService::class)->evaluate($user, $device);

        if (! $check['allowed']) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $check['message'] ?: $check['title'],
                'code' => $check['reason'],
            ], 403));
        }

        return $device;
    }
}
