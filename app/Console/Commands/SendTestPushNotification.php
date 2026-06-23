<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Push\FirebasePushService;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test
        {user? : User ID (defaults to first user with a registered FCM token)}
        {--token= : Send to a specific FCM token instead of all user tokens}
        {--title=FalconEyeGPS test : Notification title}
        {--body=Push notifications are working. : Notification body}';

    protected $description = 'Send a test FCM push notification (HTTP v1) to verify Firebase setup';

    public function handle(FirebasePushService $fcm): int
    {
        if (! $fcm->enabled()) {
            $this->error('Push is disabled. Set PUSH_NOTIFICATIONS_ENABLED=true and FIREBASE_CREDENTIALS in .env.');

            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        $body = (string) $this->option('body');
        $data = [
            'type' => 'test',
            'screen' => 'notifications',
            'time_display' => app_datetime_format(now()),
        ];

        $token = $this->option('token');
        if (is_string($token) && $token !== '') {
            $record = UserPushToken::query()->where('fcm_token', $token)->first();
            $ok = $fcm->sendToToken($token, $title, $body, $data, $record?->user_id);

            $this->line($ok ? '<info>Sent.</info>' : '<error>Failed.</error> See storage/logs/push.log');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $userId = $this->argument('user');
        if ($userId === null) {
            $userId = UserPushToken::query()->orderByDesc('last_seen_at')->value('user_id');
        }

        if (! $userId) {
            $this->error('No user with a registered FCM token. Log in on the mobile app first.');

            return self::FAILURE;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            $this->error("User {$userId} not found.");

            return self::FAILURE;
        }

        $this->info("Sending test push to user #{$user->id} ({$user->email})…");

        $result = $fcm->sendToUser($user, $title, $body, $data);
        $this->table(
            ['Sent', 'Failed', 'Skipped'],
            [[$result['sent'], $result['failed'], $result['skipped']]]
        );

        if ($result['sent'] === 0) {
            $this->warn('No successful sends. Check storage/logs/push.log and FIREBASE_* .env values.');

            return self::FAILURE;
        }

        $this->info('Done. Enable PUSH_EVENT_NOTIFICATIONS_ENABLED=true after verifying on device.');

        return self::SUCCESS;
    }
}
