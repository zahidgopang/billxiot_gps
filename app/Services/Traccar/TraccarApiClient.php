<?php

namespace App\Services\Traccar;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Traccar REST client for command delivery (session cookie auth).
 *
 * Immobilizer / movement cut-off relays require POST /api/commands/send so the
 * live Traccar process encodes and pushes the command over the device protocol.
 */
class TraccarApiClient
{
    private ?CookieJar $cookies = null;

    private bool $loggedIn = false;

    public function configured(): bool
    {
        $url = (string) config('traccar.api.url', '');
        $email = (string) config('traccar.api.email', '');
        $password = (string) config('traccar.api.password', '');

        return $url !== '' && $email !== '' && $password !== '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{ok: bool, status: int, body: mixed, message: string}
     */
    public function sendCommand(int $deviceId, string $type, array $attributes = []): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'message' => 'Traccar API is not configured.',
            ];
        }

        $login = $this->ensureSession();
        if (! ($login['ok'] ?? false)) {
            return $login;
        }

        $payload = [
            'deviceId' => $deviceId,
            'type' => $type,
            'attributes' => (object) $attributes,
        ];

        try {
            $response = $this->http()
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl().'/api/commands/send', $payload);

            $status = $response->status();
            $body = $response->json();
            if ($body === null) {
                $body = $response->body();
            }

            if ($response->successful()) {
                Log::channel('commands')->info('[traccar_api] commands/send OK', [
                    'deviceId' => $deviceId,
                    'type' => $type,
                    'attributes' => $attributes,
                    'http_status' => $status,
                ]);

                return [
                    'ok' => true,
                    'status' => $status,
                    'body' => $body,
                    'message' => 'Command accepted by Traccar.',
                ];
            }

            // Session expired — one retry after re-login.
            if (in_array($status, [401, 403], true)) {
                $this->loggedIn = false;
                $relogin = $this->ensureSession();
                if ($relogin['ok'] ?? false) {
                    $response = $this->http()
                        ->acceptJson()
                        ->asJson()
                        ->post($this->baseUrl().'/api/commands/send', $payload);
                    $status = $response->status();
                    $body = $response->json() ?? $response->body();
                    if ($response->successful()) {
                        return [
                            'ok' => true,
                            'status' => $status,
                            'body' => $body,
                            'message' => 'Command accepted by Traccar.',
                        ];
                    }
                }
            }

            $message = $this->errorMessage(is_string($body) ? $body : $response->body(), $status);

            Log::warning('traccar.api.commands_send_failed', [
                'deviceId' => $deviceId,
                'type' => $type,
                'status' => $status,
                'body' => is_string($body) ? mb_substr($body, 0, 500) : $body,
            ]);

            return [
                'ok' => false,
                'status' => $status,
                'body' => $body,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            Log::warning('traccar.api.commands_send_exception', [
                'deviceId' => $deviceId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, status: int, body: mixed, message: string}
     */
    private function ensureSession(): array
    {
        if ($this->loggedIn && $this->cookies) {
            return [
                'ok' => true,
                'status' => 200,
                'body' => null,
                'message' => 'ok',
            ];
        }

        $email = (string) config('traccar.api.email');
        $password = (string) config('traccar.api.password');
        $this->cookies = new CookieJar;

        try {
            $response = $this->http()
                ->asForm()
                ->acceptJson()
                ->post($this->baseUrl().'/api/session', [
                    'email' => $email,
                    'password' => $password,
                ]);

            if (! $response->successful()) {
                $message = $this->errorMessage($response->body(), $response->status());

                Log::warning('traccar.api.session_failed', [
                    'status' => $response->status(),
                    'email' => $email,
                ]);

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                    'message' => $message !== '' ? $message : 'Traccar login failed.',
                ];
            }

            $this->loggedIn = true;

            return [
                'ok' => true,
                'status' => $response->status(),
                'body' => $response->json(),
                'message' => 'ok',
            ];
        } catch (\Throwable $e) {
            Log::warning('traccar.api.session_exception', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);

            return [
                'ok' => false,
                'status' => 0,
                'body' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function http(): PendingRequest
    {
        $timeout = max(3, (int) config('traccar.api.timeout', 15));
        if (! $this->cookies) {
            $this->cookies = new CookieJar;
        }

        return Http::timeout($timeout)
            ->connectTimeout(min(8, $timeout))
            ->withOptions(['cookies' => $this->cookies]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('traccar.api.url', ''), '/');
    }

    private function errorMessage(string $body, int $status): string
    {
        $trim = trim($body);
        if ($trim === '') {
            return 'Traccar API HTTP '.$status;
        }

        $json = json_decode($trim, true);
        if (is_array($json)) {
            foreach (['message', 'error', 'details'] as $key) {
                if (! empty($json[$key]) && is_string($json[$key])) {
                    return $json[$key];
                }
            }
        }

        return mb_substr(preg_replace('/\s+/', ' ', $trim) ?? $trim, 0, 240);
    }
}
