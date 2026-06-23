<?php

namespace App\Http\Middleware;

use App\Services\Mobile\MobileEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileEndUser
{
    public function __construct(
        private MobileEntitlementService $entitlement,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->entitlement->isEndUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'This API is only available for end-user accounts.',
            ], 403);
        }

        return $next($request);
    }
}
