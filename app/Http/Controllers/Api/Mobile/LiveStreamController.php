<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesMobileDevice;
use App\Http\Concerns\RespondsWithMobileJson;
use Illuminate\Http\Request;

class LiveStreamController extends Controller
{
    use ResolvesMobileDevice;
    use RespondsWithMobileJson;

    public function show(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);

        $driver = config('broadcasting.default', 'null');
        $pusher = config('broadcasting.connections.pusher');

        return $this->mobileSuccess([
            'device_id' => $device->id,
            'channel' => 'device.' . $device->id,
            'event' => 'DeviceLocationUpdated',
            'driver' => $driver,
            'broadcast_auth_url' => url('/broadcasting/auth'),
            'pusher' => $driver === 'pusher' ? [
                'key' => $pusher['key'] ?? null,
                'cluster' => $pusher['options']['cluster'] ?? null,
                'host' => $pusher['options']['host'] ?? null,
                'port' => $pusher['options']['port'] ?? null,
                'scheme' => $pusher['options']['scheme'] ?? 'https',
            ] : null,
            'note' => 'Subscribe with Laravel Echo: Echo.private("device.{id}").listen(".DeviceLocationUpdated", handler)',
        ]);
    }
}
