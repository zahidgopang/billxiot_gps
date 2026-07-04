<?php

namespace App\Support\Authorization;

use App\Models\User;
use App\Services\Authorization\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ChecksFeaturePermission
{
    protected function userHas(User $user, string $permission): bool
    {
        return app(RbacService::class)->hasPermission($user, $permission);
    }

    protected function authorizeFeature(User $user, string $permission): void
    {
        abort_unless($this->userHas($user, $permission), 403);
    }

    protected function denyFeatureJson(string $message = 'Forbidden'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    protected function authorizeMobileFeature(Request $request, string $permission): ?JsonResponse
    {
        if ($this->userHas($request->user(), $permission)) {
            return null;
        }

        return $this->denyFeatureJson('Access denied');
    }
}
