<?php

namespace App\Http\Middleware;

use App\Services\Mobile\MobileEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileEntitlement
{
    public function __construct(
        private MobileEntitlementService $entitlement,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $result = $this->entitlement->evaluate($user);

        if (! $result['allowed']) {
            $status = match ($result['code']) {
                MobileEntitlementService::CODE_ACCOUNT_INACTIVE => 403,
                MobileEntitlementService::CODE_INVALID_ROLE => 403,
                default => 402,
            };

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code' => $result['code'],
            ], $status);
        }

        return $next($request);
    }
}
