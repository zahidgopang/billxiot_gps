<?php

namespace App\Support\Firebase;

final class FirebaseCredentials
{
    /**
     * Resolve FIREBASE_CREDENTIALS to an absolute filesystem path.
     */
    public static function resolvePath(?string $configured): ?string
    {
        if ($configured === null || trim($configured) === '') {
            return null;
        }

        $configured = trim($configured);

        if (self::isAbsolutePath($configured) && is_readable($configured)) {
            return $configured;
        }

        $candidates = [
            base_path($configured),
            storage_path($configured),
            storage_path('app/'.$configured),
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return base_path($configured);
    }

    private static function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
