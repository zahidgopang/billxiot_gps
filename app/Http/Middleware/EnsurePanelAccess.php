<?php

namespace App\Http\Middleware;

use App\Services\Authorization\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelAccess
{
    public function __construct(
        private RbacService $rbac,
    ) {}

    /**
     * @param  string  $panel  admin|client
     */
    public function handle(Request $request, Closure $next, string $panel = 'admin'): Response
    {
        $user = $request->user();

        if (! $user || ! $this->rbac->canAccessPanel($user)) {
            abort(403);
        }

        $userPanel = $this->rbac->roleOf($user)->panel();

        if ($panel === 'admin' && ! in_array($userPanel, ['admin'], true)) {
            return redirect()->route($this->rbac->panelRouteFor($user));
        }

        if ($panel === 'client' && $userPanel !== 'client') {
            return redirect()->route($this->rbac->panelRouteFor($user));
        }

        return $next($request);
    }
}
