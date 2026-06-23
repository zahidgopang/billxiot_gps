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
