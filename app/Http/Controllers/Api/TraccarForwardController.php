<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tracking\TraccarForwardPositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Traccar position forward (traccar.xml forward.url).
 * Traccar 6+ defaults to GET (forward.type=url); json mode uses POST.
 */
class TraccarForwardController extends Controller
{
    public function receive(Request $request, TraccarForwardPositionService $forward): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $forward->isEnabled()) {
            if (config('app.debug')) {
                Log::debug('traccar.forward skipped: mode disabled', [
                    'mode' => config('traccar.broadcast_mode'),
                    'positions' => config('traccar.broadcast_positions'),
                ]);
            }

            return response()->json(['ok' => true, 'skipped' => 'forward mode disabled']);
        }

        $payload = $this->normalizeIncomingPayload($request);
        $handled = $forward->handlePayload($payload);

        if (config('app.debug') && ! $handled) {
            Log::debug('traccar.forward received but not broadcast', [
                'method' => $request->method(),
                'keys' => array_keys($payload),
            ]);
        }

        return response()->json([
            'ok' => true,
            'broadcast' => $handled,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeIncomingPayload(Request $request): array
    {
        if ($request->isMethod('POST')) {
            $body = $request->all();

            return is_array($body) ? $body : [];
        }

        $query = $request->query();

        if ($query === []) {
            return [];
        }

        // Traccar forward.type=url sends GET query parameters (Traccar 6 default).
        $position = array_filter([
            'deviceId' => $query['deviceId'] ?? $query['device_id'] ?? null,
            'latitude' => $query['latitude'] ?? $query['lat'] ?? null,
            'longitude' => $query['longitude'] ?? $query['lon'] ?? $query['lng'] ?? null,
            'speed' => $query['speed'] ?? null,
            'course' => $query['course'] ?? null,
            'fixTime' => $query['fixTime'] ?? $query['fixtime'] ?? null,
            'attributes' => $query['attributes'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $uniqueId = (string) ($query['uniqueId'] ?? $query['id'] ?? '');

        $device = array_filter([
            'id' => $query['deviceId'] ?? null,
            'uniqueId' => $uniqueId !== '' ? $uniqueId : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return array_filter([
            'position' => $position !== [] ? $position : null,
            'device' => $device !== [] ? $device : null,
        ]);
    }

    private function authorized(Request $request): bool
    {
        $secret = (string) config('traccar.forward.secret', '');

        if ($secret === '') {
            return false;
        }

        $token = (string) ($request->header('X-Traccar-Token')
            ?? $request->header('Authorization')
            ?? $request->query('token', ''));

        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        return hash_equals($secret, $token);
    }
}
