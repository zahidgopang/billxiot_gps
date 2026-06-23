<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch UI language for the current browser session only.
     */
    public function switch(Request $request, string $locale)
    {
        if (! in_array($locale, SetLocale::SUPPORTED, true)) {
            abort(404);
        }

        session(['locale' => $locale]);

        return redirect()->back();
    }
}
