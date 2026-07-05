<?php

namespace App\Support;

final class ReverbHost
{
    /**
     * Reverb/Pusher JS expect a bare hostname (no scheme, port, or path).
     * Accepts values like "localhost", "gps.example.com", or "https://gps.example.com".
     */
    public static function normalize(?string $host, string $default = 'localhost'): string
    {
        $host = trim((string) ($host ?: $default));
        if ($host === '') {
            return $default;
        }

        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);
            if (is_string($parsed) && $parsed !== '') {
                return $parsed;
            }
        }

        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = preg_replace('#[/:].*$#', '', $host) ?? $host;

        return $host !== '' ? $host : $default;
    }
}
