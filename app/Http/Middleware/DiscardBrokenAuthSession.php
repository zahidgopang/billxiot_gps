<?php

namespace App\Http\Middleware;

use App\Support\Traccar\TraccarSchema;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Clears a session that still references a user id when tc_users is missing
 * or the row no longer exists (common on local dev without Traccar schema).
 */
class DiscardBrokenAuthSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $guard = Auth::guard('web');
        $sessionKey = $guard->getName();

        if (! $request->session()->has($sessionKey)) {
            return $next($request);
        }

        $usersTable = config('traccar.tables.users', 'tc_users');

        if (! TraccarSchema::hasTable($usersTable)) {
            $guard->logout();

            return $next($request);
        }

        try {
            if ($guard->user() === null) {
                $guard->logout();
            }
        } catch (Throwable) {
            $guard->logout();
        }

        return $next($request);
    }
}
