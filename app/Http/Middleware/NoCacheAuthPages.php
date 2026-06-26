<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks guest auth pages (login/register/password) as non-cacheable.
 *
 * Mobile browsers aggressively use the back-forward cache (bfcache) and tab
 * suspension, which can restore an auth page that still embeds an old CSRF
 * `_token`. Submitting it then fails with 419 "Page Expired" and the user never
 * logs in — even though the same flow works on desktop. Disabling caching keeps
 * the rendered token in sync with the session cookie on every visit.
 */
class NoCacheAuthPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
