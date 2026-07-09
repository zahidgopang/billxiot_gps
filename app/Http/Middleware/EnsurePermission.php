<?php

namespace App\Http\Middleware;

use App\Services\Authorization\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private RbacService $rbac,
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $permissions = array_values(array_filter($permissions));

        $allowed = $user && $permissions !== [] && collect($permissions)
            ->contains(fn (string $permission) => $this->rbac->hasPermission($user, $permission));

        if (! $allowed) {
            $message = __('app.errors.403_detail');

            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'code' => 'permission_denied',
                ], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
