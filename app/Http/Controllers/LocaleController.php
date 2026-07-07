<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch UI language — cookie-based (no heavy session write on each toggle).
     */
    public function switch(Request $request, string $locale)
    {
        if (! in_array($locale, SetLocale::SUPPORTED, true)) {
            abort(404);
        }

        if ($request->session()->get('locale') !== $locale) {
            $request->session()->put('locale', $locale);
        }

        return redirect()
            ->back()
            ->withCookie(cookie(
                SetLocale::LOCALE_COOKIE,
                $locale,
                60 * 24 * 365,
                '/',
                null,
                $request->secure(),
                false,
                false,
                'lax',
            ));
    }
}
