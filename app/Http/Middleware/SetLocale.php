<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $queryLang = $request->query('lang');
        if (in_array($queryLang, self::SUPPORTED, true)) {
            session(['locale' => $queryLang]);
        }

        $locale = session('locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $this->defaultLocale();
            session(['locale' => $locale]);
        }

        App::setLocale($locale);

        return $next($request);
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
