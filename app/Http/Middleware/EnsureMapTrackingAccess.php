<?php

namespace App\Http\Middleware;

use App\Services\Authorization\TenantScopeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMapTrackingAccess
{
    public function __construct(
        private TenantScopeService $tenantScope,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->tenantScope->actorMayTrackMaps($user)) {
            abort(403, __('app.forms.map_tracking_disabled'));
        }

        return $next($request);
    }
}
