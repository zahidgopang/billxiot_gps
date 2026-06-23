<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Load-balancer / uptime probe. Does not use the web middleware stack.
 */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => true,
        ];
        $status = 200;

        try {
            DB::connection()->getPdo();
            DB::connection()->select('select 1');
            $checks['database'] = true;
        } catch (Throwable $e) {
            $checks['database'] = false;
            $checks['database_error'] = config('app.debug')
                ? $e->getMessage()
                : 'unavailable';
            $status = 503;
        }

        return response()->json([
            'status' => $status === 200 ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $status);
    }
}
