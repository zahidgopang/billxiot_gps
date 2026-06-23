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

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $this->rbac->hasPermission($user, $permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
