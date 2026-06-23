<?php

namespace App\Support\Firebase;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * OAuth2 access tokens for FCM HTTP v1 using a Firebase service account JSON file.
 */
final class ServiceAccountTokenProvider
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function isConfigured(): bool
    {
        if (! config('firebase.enabled')) {
            return false;
        }

        $path = config('firebase.credentials');

        return is_string($path) && is_readable($path);
    }

    public function projectId(): string
    {
        $configured = config('firebase.project_id');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (string) ($this->loadCredentials()['project_id'] ?? '');
    }

    public function getAccessToken(): string
    {
        return Cache::remember('firebase.fcm_access_token', 3300, function () {
            return $this->fetchAccessToken();
        });
    }

    public function forgetCachedToken(): void
    {
        Cache::forget('firebase.fcm_access_token');
    }

    private function fetchAccessToken(): string
    {
        $credentials = $this->loadCredentials();
        $jwt = $this->buildJwt($credentials);

        $response = Http::asForm()
            ->timeout(15)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Firebase token request failed: '.$response->body());
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Firebase token response missing access_token.');
        }

        return $token;
    }

  /**
     * @param  array<string, mixed>  $credentials
     */
    private function buildJwt(array $credentials): string
    {
        $email = (string) ($credentials['client_email'] ?? '');
        $privateKey = $credentials['private_key'] ?? null;

        if ($email === '' || ! is_string($privateKey) || $privateKey === '') {
            throw new \RuntimeException('Firebase credentials JSON is missing client_email or private_key.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $email,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $input = "{$header}.{$payload}";
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new \RuntimeException('Invalid Firebase service account private key.');
        }

        $signature = '';
        if (! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign Firebase JWT.');
        }

        return $input.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCredentials(): array
    {
        $path = config('firebase.credentials');
        if (! is_string($path) || ! is_readable($path)) {
            throw new \RuntimeException('Firebase credentials file is missing or unreadable: '.$path);
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            throw new \RuntimeException('Firebase credentials JSON is invalid.');
        }

        return $data;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
