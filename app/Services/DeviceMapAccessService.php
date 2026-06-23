<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Traccar\TraccarDeviceAccessService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
class DeviceMapAccessService
{
    private const PAYLOAD_VERSION = 2;

    private const GRANT_TTL_SECONDS = 300;

    private const SESSION_TTL_SECONDS = 7200;

    public function __construct(
        private DeviceAccessService $deviceAccess,
        private TraccarDeviceAccessService $traccarDevices,
    ) {}

    /**
     * Issue a one-time map grant and return a unique encrypted URL token.
     */
    public function issueGrant(Device $device, User $user, bool $forAdmin = false): string
    {
        $grantId = (string) Str::uuid();
        $nonce = Str::random(32);

        Cache::put($this->grantCacheKey($grantId), [
            'device_id' => (int) $device->id,
            'user_id' => (int) $user->id,
            'admin' => $forAdmin,
            'nonce' => $nonce,
            'issued_at' => now()->timestamp,
        ], self::GRANT_TTL_SECONDS);

        return $this->encodeToken($grantId, (int) $device->id, $nonce);
    }

    /**
     * Validate grant on first map open, or reuse session on refresh (same token).
     */
    public function activateMapPage(string $token, User $user): Device
    {
        $parsed = $this->parseToken($token);
        $forAdmin = $this->isAdminMapRequest();

        if ($this->hasValidMapSessionForToken($token, $user, $parsed['device_id'])) {
            $device = $this->resolveMapDevice($parsed['device_id'], $user, $forAdmin);
            $this->assertUserMayViewDevice($user, $device, $forAdmin);
            $this->touchMapSession($token);

            return $device;
        }

        $grant = $this->pullGrant($parsed['grant_id']);

        if (! $grant) {
            abort(404, 'This map link has expired or was already used. Please open the map again from your device list.');
        }

        if ((int) $grant['device_id'] !== $parsed['device_id']) {
            abort(404);
        }

        $forAdmin = ! empty($grant['admin']);

        if ($forAdmin) {
            $device = Device::with(['user', 'subscription'])->findOrFail($parsed['device_id']);
            $this->assertFleetMapAccess($user, $device);
            $this->bindMapSession($token, $device, $user, true);

            return $device;
        }

        if ((int) $grant['user_id'] !== (int) $user->id) {
            abort(403);
        }

        $device = $this->traccarDevices->queryForUser($user)
            ->with(['subscription', 'user'])
            ->findOrFail($parsed['device_id']);

        $this->assertUserMayViewDevice($user, $device, false);
        $this->bindMapSession($token, $device, $user, false);

        return $device;
    }

    public function endMapSession(): void
    {
        session()->forget('map_access');
    }

    public function touchMapSession(string $token): void
    {
        $session = session('map_access');

        if (! is_array($session)) {
            return;
        }

        if (($session['token_hash'] ?? '') !== hash('sha256', $token)) {
            return;
        }

        $session['expires_at'] = now()->addSeconds(self::SESSION_TTL_SECONDS)->timestamp;
        session()->put('map_access', $session);
    }

    /**
     * Validate map API / geofence requests against session bound at page load.
     */
    public function assertMapApiAccess(string $token, User $user): Device
    {
        $parsed = $this->parseToken($token);
        $session = session('map_access');

        if (! is_array($session)) {
            abort(403, 'Map session expired. Please open the map again from your device list.');
        }

        if (now()->timestamp > (int) ($session['expires_at'] ?? 0)) {
            session()->forget('map_access');
            abort(403, 'Map session expired. Please open the map again from your device list.');
        }

        if ((int) ($session['device_id'] ?? 0) !== $parsed['device_id']) {
            abort(403);
        }

        if ((int) ($session['user_id'] ?? 0) !== (int) $user->id) {
            abort(403);
        }

        if (($session['token_hash'] ?? '') !== hash('sha256', $token)) {
            abort(403, 'Invalid map session.');
        }

        $this->touchMapSession($token);

        $isAdminSession = ! empty($session['admin']);

        if ($isAdminSession) {
            $device = Device::with(['user', 'subscription'])->findOrFail($parsed['device_id']);
            $this->assertFleetMapAccess($user, $device);

            return $device;
        }

        $device = $this->traccarDevices->queryForUser($user)
            ->with(['subscription', 'user'])
            ->findOrFail($parsed['device_id']);

        $check = $this->deviceAccess->evaluate($user, $device);

        if (! $check['allowed']) {
            throw new HttpResponseException(response()->json([
                'error' => $check['reason'],
                'title' => $check['title'],
                'message' => $check['message'],
                'redirect' => route('user.devices.index'),
            ], 403));
        }

        return $device;
    }

    public function isAdminMapRequest(?Request $request = null): bool
    {
        return $this->isFleetMapRequest($request);
    }

    public function isFleetMapRequest(?Request $request = null): bool
    {
        $request ??= request();

        return $request->routeIs(
            'admin.device.*',
            'admin.locations.*',
            'client.device.*',
            'client.locations.*',
        );
    }

    private function assertFleetMapAccess(User $user, Device $device): void
    {
        if (! app(RbacService::class)->canAccessPanel($user)) {
            abort(403);
        }

        if (! $this->traccarDevices->userCanAccessDevice($user, $device)) {
            abort(403);
        }
    }

    /**
     * @return array{grant_id: string, device_id: int, nonce: string}
     */
    public function parseToken(string $token): array
    {
        if ($token === '' || ctype_digit($token)) {
            abort(404);
        }

        try {
            $json = Crypt::decryptString($this->fromUrlSafeBase64($token));
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            $version = (int) ($payload['v'] ?? 0);
            $deviceId = (int) ($payload['d'] ?? 0);
            $grantId = (string) ($payload['g'] ?? '');
            $nonce = (string) ($payload['n'] ?? '');

            if ($version !== self::PAYLOAD_VERSION || $deviceId < 1 || $grantId === '') {
                abort(404);
            }

            return [
                'grant_id' => $grantId,
                'device_id' => $deviceId,
                'nonce' => $nonce,
            ];
        } catch (DecryptException|\JsonException|\Throwable) {
            abort(404);
        }
    }

    private function encodeToken(string $grantId, int $deviceId, string $nonce): string
    {
        $payload = json_encode([
            'g' => $grantId,
            'd' => $deviceId,
            'n' => $nonce,
            'v' => self::PAYLOAD_VERSION,
        ]);

        return $this->toUrlSafeBase64(Crypt::encryptString($payload));
    }

    private function pullGrant(string $grantId): ?array
    {
        return Cache::pull($this->grantCacheKey($grantId));
    }

    private function grantCacheKey(string $grantId): string
    {
        return 'map_grant:' . $grantId;
    }

    private function hasValidMapSessionForToken(string $token, User $user, int $deviceId): bool
    {
        $session = session('map_access');

        if (! is_array($session)) {
            return false;
        }

        if (now()->timestamp > (int) ($session['expires_at'] ?? 0)) {
            session()->forget('map_access');

            return false;
        }

        if ((int) ($session['device_id'] ?? 0) !== $deviceId) {
            return false;
        }

        if ((int) ($session['user_id'] ?? 0) !== (int) $user->id) {
            return false;
        }

        if (($session['token_hash'] ?? '') !== hash('sha256', $token)) {
            return false;
        }

        return true;
    }

    private function resolveMapDevice(int $deviceId, User $user, bool $forAdmin): Device
    {
        if ($forAdmin) {
            $device = Device::with(['user', 'subscription'])->findOrFail($deviceId);
            $this->assertFleetMapAccess($user, $device);

            return $device;
        }

        return $this->traccarDevices->queryForUser($user)
            ->with(['subscription', 'user'])
            ->findOrFail($deviceId);
    }

    private function assertUserMayViewDevice(User $user, Device $device, bool $forAdmin): void
    {
        if ($forAdmin) {
            $this->assertFleetMapAccess($user, $device);

            return;
        }

        $check = $this->deviceAccess->evaluate($user, $device);

        if (! $check['allowed']) {
            throw new HttpResponseException(
                redirect()
                    ->route('user.devices.index')
                    ->with('access_denied_title', $check['title'])
                    ->with('access_denied_message', $check['message'])
                    ->with('subscription_device', $device->name)
                    ->with('access_denied_reason', $check['reason'])
            );
        }
    }

    private function bindMapSession(string $token, Device $device, User $user, bool $admin): void
    {
        session()->put('map_access', [
            'device_id' => (int) $device->id,
            'user_id' => (int) $user->id,
            'admin' => $admin,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds(self::SESSION_TTL_SECONDS)->timestamp,
        ]);
    }

    private function toUrlSafeBase64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function fromUrlSafeBase64(string $token): string
    {
        $b64 = strtr($token, '-_', '+/');
        $pad = strlen($b64) % 4;

        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($b64, true);

        if ($decoded === false) {
            throw new DecryptException('Invalid map token encoding.');
        }

        return $decoded;
    }
}
