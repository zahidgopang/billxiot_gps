<?php

namespace App\Http\Middleware;

use App\Support\Traccar\TraccarSchema;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Login/logout manage session lifecycle — skip schema/user probes here.
        if ($request->routeIs('login', 'logout', 'register', 'password.*', 'locale.switch')) {
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

        $userId = $request->session()->get($sessionKey);

        try {
            $exists = is_numeric($userId)
                && DB::table($usersTable)->where('id', (int) $userId)->exists();

            if (! $exists) {
                $guard->logout();
            }
        } catch (Throwable) {
            $guard->logout();
        }

        return $next($request);
    }
}
