<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public const LOCALE_COOKIE = 'billx_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $queryLang = $request->query('lang');
        if (in_array($queryLang, self::SUPPORTED, true)) {
            $this->rememberLocale($request, $queryLang);
        }

        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        $htmlDir = $locale === 'ar' ? 'rtl' : 'ltr';
        View::share('htmlLang', $locale);
        View::share('htmlDir', $htmlDir);
        View::share('isRtl', $locale === 'ar');

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $cookieLocale = $request->cookie(self::LOCALE_COOKIE);
        if (in_array($cookieLocale, self::SUPPORTED, true)) {
            return $cookieLocale;
        }

        $sessionLocale = $request->session()->get('locale');
        if (in_array($sessionLocale, self::SUPPORTED, true)) {
            return $sessionLocale;
        }

        return $this->defaultLocale();
    }

    private function rememberLocale(Request $request, string $locale): void
    {
        if ($request->session()->get('locale') !== $locale) {
            $request->session()->put('locale', $locale);
        }
    }

    private function defaultLocale(): string
    {
        $locale = config('app.locale', 'ar');

        if (! in_array($locale, self::SUPPORTED, true)) {
            return 'ar';
        }

        return $locale;
    }
}
