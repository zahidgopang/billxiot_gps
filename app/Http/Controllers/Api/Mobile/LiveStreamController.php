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
        $reverb = config('broadcasting.connections.reverb');

        // Reverb speaks the Pusher protocol, so the mobile client (pusher_channels_flutter)
        // connects the same way — it just needs host/port/scheme of our own server.
        $realtime = $driver === 'reverb' ? [
            'key'    => $reverb['key'] ?? null,
            'host'   => $reverb['options']['host'] ?? null,
            'port'   => $reverb['options']['port'] ?? 443,
            'scheme' => $reverb['options']['scheme'] ?? 'https',
            'useTLS' => ($reverb['options']['scheme'] ?? 'https') === 'https',
        ] : null;

        return $this->mobileSuccess([
            'device_id' => $device->id,
            'channel' => 'device.' . $device->id,
            'event' => 'DeviceLocationUpdated',
            'driver' => $driver,
            // Token-guarded endpoint (api.php) — the web /broadcasting/auth is
            // session-only and would reject the mobile bearer token.
            'broadcast_auth_url' => url('/api/broadcasting/auth'),
            // `reverb` block (preferred). `pusher` kept as an alias for older app builds.
            'reverb' => $realtime,
            'pusher' => $realtime,
            'note' => 'Subscribe with Laravel Echo: Echo.private("device.{id}").listen(".DeviceLocationUpdated", handler)',
        ]);
    }
}
