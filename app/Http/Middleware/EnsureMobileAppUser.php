<?php

namespace App\Http\Middleware;

use App\Services\Mobile\MobileEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow end-users and staff (admin/client) to use the mobile API.
 */
class EnsureMobileAppUser
{
    public function __construct(
        private MobileEntitlementService $entitlement,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->entitlement->canAccessMobileApp($user)) {
            return response()->json([
                'success' => false,
                'message' => 'This account is not allowed to use the mobile app.',
            ], 403);
        }

        return $next($request);
    }
}
