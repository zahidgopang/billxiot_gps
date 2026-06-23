<?php

namespace App\Services\Push;

use App\Models\PushNotificationLog;
use Illuminate\Support\Facades\Log;

class PushNotificationLogger
{
    public function attempt(
        ?int $userId,
        ?string $token,
        ?string $pushType,
        string $title,
        string $body,
        array $data = [],
    ): void {
        $context = $this->context($userId, $token, $pushType, $title, $body, $data);
        Log::channel($this->channel())->info('FCM send attempt', $context);
    }

    public function sent(
        ?int $userId,
        ?string $token,
        ?string $pushType,
        string $title,
        string $body,
        array $data = [],
        ?int $httpStatus = 200,
        ?array $response = null,
    ): void {
        $context = $this->context($userId, $token, $pushType, $title, $body, $data);
        Log::channel($this->channel())->info('FCM send success', $context);

        $this->persist(
            userId: $userId,
            token: $token,
            pushType: $pushType,
            title: $title,
            body: $body,
            status: PushNotificationLog::STATUS_SENT,
            httpStatus: $httpStatus,
            data: $data,
            response: $response,
        );
    }

    public function failed(
        ?int $userId,
        ?string $token,
        ?string $pushType,
        string $title,
        string $body,
        array $data = [],
        ?int $httpStatus = null,
        ?string $errorMessage = null,
        ?array $response = null,
    ): void {
        $context = array_merge(
            $this->context($userId, $token, $pushType, $title, $body, $data),
            ['http_status' => $httpStatus, 'error' => $errorMessage],
        );
        Log::channel($this->channel())->warning('FCM send failed', $context);

        $this->persist(
            userId: $userId,
            token: $token,
            pushType: $pushType,
            title: $title,
            body: $body,
            status: PushNotificationLog::STATUS_FAILED,
            httpStatus: $httpStatus,
            errorMessage: $errorMessage,
            data: $data,
            response: $response,
        );
    }

    public function skipped(string $reason, array $context = []): void
    {
        Log::channel($this->channel())->info('FCM send skipped', array_merge(['reason' => $reason], $context));

        PushNotificationLog::query()->create([
            'status' => PushNotificationLog::STATUS_SKIPPED,
            'title' => $context['title'] ?? null,
            'body' => $context['body'] ?? null,
            'push_type' => $context['push_type'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'error_message' => $reason,
            'data' => $context['data'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function context(
        ?int $userId,
        ?string $token,
        ?string $pushType,
        string $title,
        string $body,
        array $data,
    ): array {
        return [
            'user_id' => $userId,
            'token_hash' => $token ? hash('sha256', $token) : null,
            'push_type' => $pushType,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function persist(
        ?int $userId,
        ?string $token,
        ?string $pushType,
        string $title,
        string $body,
        string $status,
        ?int $httpStatus,
        array $data,
        ?string $errorMessage = null,
        ?array $response = null,
    ): void {
        PushNotificationLog::query()->create([
            'user_id' => $userId,
            'fcm_token_hash' => $token ? hash('sha256', $token) : null,
            'push_type' => $pushType,
            'title' => $title,
            'body' => $body,
            'status' => $status,
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
            'response' => $response,
            'data' => $data !== [] ? $data : null,
            'created_at' => now(),
        ]);
    }

    private function channel(): string
    {
        return (string) config('firebase.log_channel', 'push');
    }
}
