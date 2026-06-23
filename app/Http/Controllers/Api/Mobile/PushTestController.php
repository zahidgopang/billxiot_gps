<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Push\FirebasePushService;
use Illuminate\Http\Request;

class PushTestController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private FirebasePushService $fcm,
    ) {}

  /**
     * POST /api/push-test — send a test notification to the authenticated user.
     */
    public function send(Request $request)
    {
        if (! $this->fcm->enabled()) {
            return $this->mobileError(
                'Push notifications are disabled. Set PUSH_NOTIFICATIONS_ENABLED=true and configure FIREBASE_CREDENTIALS.',
                503,
                'push_disabled'
            );
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:1000',
            'fcm_token' => 'nullable|string|max:512',
        ]);

        $user = $request->user();
        $title = $validated['title'] ?? 'FalconEyeGPS test';
        $body = $validated['body'] ?? 'Push notifications are working.';
        $data = [
            'type' => 'test',
            'screen' => 'notifications',
            'time_display' => app_datetime_format(now()),
        ];

        if (! empty($validated['fcm_token'])) {
            $ok = $this->fcm->sendToToken(
                $validated['fcm_token'],
                $title,
                $body,
                $data,
                (int) $user->id,
            );

            return $this->mobileSuccess([
                'sent' => $ok ? 1 : 0,
                'failed' => $ok ? 0 : 1,
                'skipped' => 0,
                'message' => $ok
                    ? 'Test notification sent to the provided token.'
                    : 'Failed to send test notification. Check storage/logs/push.log.',
            ]);
        }

        $result = $this->fcm->sendToUser($user, $title, $body, $data);

        if ($result['sent'] === 0 && $result['failed'] === 0) {
            return $this->mobileError(
                'No FCM token registered for this account. Log in on the mobile app first.',
                422,
                'no_push_token'
            );
        }

        return $this->mobileSuccess(array_merge($result, [
            'message' => $result['sent'] > 0
                ? 'Test notification sent.'
                : 'Failed to send test notification. Check storage/logs/push.log.',
        ]));
    }
}
