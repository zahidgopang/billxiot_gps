<?php

namespace App\Http\Middleware;

use App\Services\Traccar\TraccarUserAccessService;
use App\Support\Traccar\TraccarMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrackerAccess
{
    public function __construct(
        private TraccarUserAccessService $trackerUsers,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || app(\App\Services\Authorization\RbacService::class)->canAccessPanel($user) || ! TraccarMode::readsTraccar()) {
            return $next($request);
        }

        $this->trackerUsers->pruneStaleMapForUser($user);

        $active = $this->trackerUsers->hasTrackerAccount($user);

        view()->share('trackerAccountActive', $active);
        view()->share('trackerDisplayName', $this->trackerUsers->displayName($user));

        if (! $active && $this->isTrackerRoute($request)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error' => 'tracker_account_missing',
                    'title' => 'Tracking not available',
                    'message' => 'Your account is not linked to FalconEyeGPS fleet tracking. Contact support if you need fleet access.',
                    'redirect' => route('user.dashboard'),
                ], 403);
            }

            if (! $request->routeIs('user.dashboard')) {
                return redirect()
                    ->route('user.dashboard')
                    ->with('tracker_unavailable', true);
            }
        }

        return $next($request);
    }

    private function isTrackerRoute(Request $request): bool
    {
        return $request->routeIs(
            'user.devices.*',
            'user.alerts.*',
            'user.device.*',
            'user.devices.launch-map',
            'map.tour.preference',
            'map.session.end'
        );
    }
}
