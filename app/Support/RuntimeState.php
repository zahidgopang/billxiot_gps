<?php

namespace App\Support;

/**
 * Small key/value state on disk — survives cache:clear and optimize:clear.
 */
final class RuntimeState
{
    public static function getInt(string $key, int $default = 0): int
    {
        $path = self::path($key);

        if (! is_file($path)) {
            return $default;
        }

        $raw = trim((string) file_get_contents($path));

        return is_numeric($raw) ? (int) $raw : $default;
    }

    public static function putInt(string $key, int $value): void
    {
        $path = self::path($key);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, (string) $value, LOCK_EX);
    }

    private static function path(string $key): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?: 'state';

        return storage_path('app/runtime-state/'.$safe.'.txt');
    }
}
