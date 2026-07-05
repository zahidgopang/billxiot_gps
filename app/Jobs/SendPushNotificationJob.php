<?php

namespace App\Jobs;

use App\Services\Push\FirebasePushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends FCM push after the HTTP response (GPS ingest / live poll must not wait on Firebase).
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, string>  $data
     */
    public function __construct(
        public array $userIds,
        public string $title,
        public string $body,
        public array $data,
    ) {}

    public function handle(FirebasePushService $fcm): void
    {
        $fcm->sendToUsers($this->userIds, $this->title, $this->body, $this->data);
    }
}
