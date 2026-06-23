<?php

use Illuminate\Support\Facades\File;

if (! function_exists('protected_js')) {
    /**
     * URL for a JS file — obfuscated build in production when available.
     */
    function protected_js(string $filename, ?string $version = null): string
    {
        $filename = ltrim(str_replace(['\\', '..'], '', $filename), '/');

        if (config('assets.protection_enabled')) {
            $protected = public_path('assets/protected/js/' . $filename);

            if (File::isFile($protected)) {
                $v = $version ?? (string) filemtime($protected);

                return asset('assets/protected/js/' . $filename) . '?v=' . $v;
            }
        }

        $plain = public_path('js/' . $filename);
        $v = $version ?? (File::isFile($plain) ? (string) filemtime($plain) : '1');

        return asset('js/' . $filename) . '?v=' . $v;
    }
}

if (! function_exists('asset_protection_enabled')) {
    function asset_protection_enabled(): bool
    {
        return (bool) config('assets.protection_enabled');
    }
}

if (! function_exists('client_hardening_enabled')) {
    function client_hardening_enabled(): bool
    {
        return (bool) config('assets.client_hardening');
    }
}

if (! function_exists('app_datetime_format')) {
    /**
     * Format a timestamp for display (Pakistan time, 12-hour AM/PM).
     *
     * @param  \Carbon\CarbonInterface|\DateTimeInterface|string|null  $dt
     */
    function app_datetime_format(mixed $dt, string $style = 'display'): ?string
    {
        if ($dt === null || $dt === '') {
            return null;
        }

        if (is_string($dt)) {
            $dt = \App\Support\DateTime\AppDateTime::parse($dt);
        } elseif (! $dt instanceof \Carbon\CarbonInterface) {
            $dt = \Carbon\Carbon::parse($dt);
        }

        return \App\Support\DateTime\AppDateTime::format($dt, $style);
    }
}

if (! function_exists('app_datetime_api')) {
    function app_datetime_api(mixed $dt): ?string
    {
        if ($dt === null || $dt === '') {
            return null;
        }

        if (is_string($dt)) {
            $dt = \App\Support\DateTime\AppDateTime::parse($dt);
        } elseif (! $dt instanceof \Carbon\CarbonInterface) {
            $dt = \Carbon\Carbon::parse($dt);
        }

        return \App\Support\DateTime\AppDateTime::toApi($dt);
    }
}
