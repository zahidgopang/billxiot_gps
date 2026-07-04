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
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
