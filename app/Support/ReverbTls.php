<?php

namespace App\Support;

final class ReverbTls
{
    /**
     * SSL context options for the Reverb server process.
     * Return an empty array when TLS should be disabled (e.g. production behind Nginx).
     *
     * @return array<string, mixed>
     */
    public static function serverOptions(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $cert = self::resolveCertPath();
        if ($cert === null) {
            return [];
        }

        $key = self::resolveKeyPath();

        $options = array_filter([
            'local_cert' => $cert,
            'local_pk' => ($key !== null && is_file($key)) ? $key : null,
            'verify_peer' => false,
            'allow_self_signed' => true,
        ], static fn ($value) => $value !== null && $value !== '');

        return $options;
    }

    public static function enabled(): bool
    {
        if (filter_var(env('REVERB_TLS', null), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false) {
            return false;
        }

        if (filter_var(env('REVERB_TLS', null), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true) {
            return self::resolveCertPath() !== null;
        }

        // Local Laragon: auto-enable TLS when HTTPS is configured and certs exist.
        if (app()->environment('local')
            && env('REVERB_SCHEME', 'https') === 'https'
            && self::resolveCertPath() !== null) {
            return true;
        }

        return false;
    }

    private static function resolveCertPath(): ?string
    {
        $configured = self::normalizePath(env('REVERB_TLS_CERT'));
        if ($configured !== null && is_file($configured)) {
            return $configured;
        }

        if (! app()->environment('local')) {
            return null;
        }

        $base = self::normalizePath(env('LARAGON_SSL_PATH', 'D:/laragon/etc/ssl'));
        if ($base === null) {
            return null;
        }

        $autoCert = $base . DIRECTORY_SEPARATOR . 'laragon.crt';
        if (is_file($autoCert)) {
            return $autoCert;
        }

        return null;
    }

    private static function resolveKeyPath(): ?string
    {
        $configured = self::normalizePath(env('REVERB_TLS_KEY'));
        if ($configured !== null && is_file($configured)) {
            return $configured;
        }

        if (! app()->environment('local')) {
            return null;
        }

        $base = self::normalizePath(env('LARAGON_SSL_PATH', 'D:/laragon/etc/ssl'));
        if ($base === null) {
            return null;
        }

        $autoKey = $base . DIRECTORY_SEPARATOR . 'laragon.key';
        if (is_file($autoKey)) {
            return $autoKey;
        }

        return null;
    }

    private static function normalizePath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        return $path !== '' ? $path : null;
    }
}
