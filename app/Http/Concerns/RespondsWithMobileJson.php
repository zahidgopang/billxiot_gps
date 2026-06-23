<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondsWithMobileJson
{
    protected function mobileSuccess(mixed $data = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function mobileError(string $message, int $status = 400, ?string $code = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload, $status);
    }
}
