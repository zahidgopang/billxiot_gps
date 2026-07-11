<?php

namespace App\Http\Middleware;

use App\Services\DeviceAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function __construct(
        private DeviceAccessService $access
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || app(\App\Services\Authorization\RbacService::class)->canAccessPanel($user)) {
            return $next($request);
        }

        if ($this->access->isUserActive($user)) {
            return $next($request);
        }

        $message = 'Your account is inactive. You cannot use map tracking until an administrator reactivates your account.';

        // /user/devices is the inactive landing page (shows access_denied flash).
        // Never redirect away from it — that caused ERR_TOO_MANY_REDIRECTS.
        if ($request->routeIs('user.devices.index')) {
            if (! $request->session()->has('access_denied_message')) {
                $request->session()->now('access_denied_title', 'Account inactive');
                $request->session()->now('access_denied_message', $message);
            }

            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => 'user_inactive',
                'title' => 'Account inactive',
                'message' => $message,
                'redirect' => route('user.devices.index'),
            ], 403);
        }

        return redirect()
            ->route('user.devices.index')
            ->with('access_denied_title', 'Account inactive')
            ->with('access_denied_message', $message);
    }
}
