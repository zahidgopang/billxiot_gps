<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tracking\TraccarForwardPositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Traccar position forward POSTs (traccar.xml forward.url).
 * Broadcasts DeviceLocationUpdated to Reverb immediately — no DB polling scheduler.
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
                \Illuminate\Support\Facades\Log::debug('traccar.forward skipped: mode disabled', [
                    'mode' => config('traccar.broadcast_mode'),
                    'positions' => config('traccar.broadcast_positions'),
                ]);
            }

            return response()->json(['ok' => true, 'skipped' => 'forward mode disabled']);
        }

        $handled = $forward->handlePayload($request->all());

        if (config('app.debug') && ! $handled) {
            \Illuminate\Support\Facades\Log::debug('traccar.forward received but not broadcast', [
                'keys' => array_keys($request->all()),
            ]);
        }

        return response()->json([
            'ok' => true,
            'broadcast' => $handled,
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
