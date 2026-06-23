<?php

namespace App\Services\Push;

use App\Models\User;
use App\Models\UserPushToken;
use App\Support\Firebase\ServiceAccountTokenProvider;
use Illuminate\Support\Facades\Http;

class FirebasePushService
{
    public function __construct(
        private ServiceAccountTokenProvider $tokens,
        private PushNotificationLogger $logger,
    ) {}

    public function enabled(): bool
    {
        return $this->tokens->isConfigured();
    }

    /**
     * Send to all registered tokens for a user.
     *
     * @param  array<string, string>  $data
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToUser(User|int $user, string $title, string $body, array $data = []): array
    {
        $userId = $user instanceof User ? (int) $user->id : (int) $user;

        $tokens = UserPushToken::query()
            ->where('user_id', $userId)
            ->pluck('fcm_token')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($tokens === []) {
            $this->logger->skipped('No FCM tokens for user', [
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'push_type' => $data['type'] ?? null,
                'data' => $data,
            ]);

            return ['sent' => 0, 'failed' => 0, 'skipped' => 1];
        }

        return $this->sendToMultipleTokens($tokens, $title, $body, $data, $userId);
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, string>  $data
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $totals = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach (array_unique($userIds) as $userId) {
            $result = $this->sendToUser((int) $userId, $title, $body, $data);
            foreach ($result as $key => $count) {
                $totals[$key] += $count;
            }
        }

        return $totals;
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToMultipleTokens(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        ?int $userId = null,
    ): array {
        $totals = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach (array_unique(array_filter($tokens)) as $token) {
            if ($this->sendToToken($token, $title, $body, $data, $userId)) {
                $totals['sent']++;
            } else {
                $totals['failed']++;
            }
        }

        return $totals;
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data = [],
        ?int $userId = null,
    ): bool {
        $pushType = $data['type'] ?? null;

        if (! $this->enabled()) {
            $this->logger->skipped('Push notifications disabled', [
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'push_type' => $pushType,
                'data' => $data,
            ]);

            return false;
        }

        $this->logger->attempt($userId, $token, $pushType, $title, $body, $data);

        $projectId = $this->tokens->projectId();
        if ($projectId === '') {
            $this->logger->failed(
                $userId,
                $token,
                $pushType,
                $title,
                $body,
                $data,
                errorMessage: 'FIREBASE_PROJECT_ID is not configured',
            );

            return false;
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', array_merge($data, [
                    'title' => $title,
                    'body' => $body,
                ])),
                'android' => [
                    'priority' => 'HIGH',
                ],
            ],
        ];

        try {
            $accessToken = $this->tokens->getAccessToken();
        } catch (\Throwable $e) {
            report($e);
            $this->logger->failed(
                $userId,
                $token,
                $pushType,
                $title,
                $body,
                $data,
                errorMessage: $e->getMessage(),
            );

            return false;
        }

        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        if ($response->successful()) {
            $this->logger->sent(
                $userId,
                $token,
                $pushType,
                $title,
                $body,
                $data,
                $response->status(),
                $response->json(),
            );

            return true;
        }

        $bodyText = $response->body();
        if ($this->isInvalidTokenResponse($bodyText)) {
            UserPushToken::query()->where('fcm_token', $token)->delete();
        } elseif ($response->status() === 401) {
            $this->tokens->forgetCachedToken();
        }

        $this->logger->failed(
            $userId,
            $token,
            $pushType,
            $title,
            $body,
            $data,
            $response->status(),
            $bodyText,
            $response->json(),
        );

        return false;
    }

    private function isInvalidTokenResponse(string $body): bool
    {
        $lower = strtolower($body);

        return str_contains($lower, 'not_found')
            || str_contains($lower, 'unregistered')
            || str_contains($lower, 'invalid_argument');
    }
}
