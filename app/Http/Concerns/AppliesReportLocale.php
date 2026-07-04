<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;

trait AppliesReportLocale
{
    protected function applyReportLocale(Request $request): string
    {
        $lang = strtolower(trim((string) $request->query('lang', $request->input('lang', ''))));

        if (! in_array($lang, ['en', 'ar'], true)) {
            $lang = app()->getLocale();
        }

        if (! in_array($lang, ['en', 'ar'], true)) {
            $lang = 'en';
        }

        app()->setLocale($lang);

        return $lang;
    }
}
