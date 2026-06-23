<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictScrapers
{
    /** @var list<string> */
    private const BLOCKED_UA_FRAGMENTS = [
        'curl/',
        'wget/',
        'python-requests',
        'python-urllib',
        'scrapy',
        'httpclient',
        'go-http-client',
        'java/',
        'libwww-perl',
        'httpx/',
        'aiohttp',
        'node-fetch',
        'axios/',
        'headlesschrome',
        'phantomjs',
        'semrushbot',
        'ahrefsbot',
        'petalbot',
        'bytespider',
        'gptbot',
        'claudebot',
        'anthropic-ai',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('assets.block_scrapers')) {
            return $next($request);
        }

        if (! $this->isProtectedPath($request)) {
            return $next($request);
        }

        $ua = strtolower($request->userAgent() ?? '');

        if ($ua === '') {
            return $next($request);
        }

        foreach (self::BLOCKED_UA_FRAGMENTS as $fragment) {
            if (str_contains($ua, $fragment)) {
                abort(403, 'Automated access is not permitted.');
            }
        }

        return $next($request);
    }

    private function isProtectedPath(Request $request): bool
    {
        return $request->is(
            'user',
            'user/*',
            'admin',
            'admin/*',
            'dashboard',
            'profile',
            'profile/*'
        );
    }
}
