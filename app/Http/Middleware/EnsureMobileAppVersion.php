<?php

namespace App\Http\Middleware;

use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Mobile\MobileAppVersionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAppVersion
{
    use RespondsWithMobileJson;

    public function __construct(
        private MobileAppVersionService $versions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $client = $this->versions->readClientVersion($request);

        if (! $this->versions->isSupported($client['version'], $client['build'])) {
            return $this->mobileError(
                $this->versions->updateRequiredMessage(),
                426,
                'app_update_required',
            );
        }

        return $next($request);
    }
}
