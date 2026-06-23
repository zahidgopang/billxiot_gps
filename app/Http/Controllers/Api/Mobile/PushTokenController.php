<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\UserPushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    use RespondsWithMobileJson;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:512',
            'platform' => 'nullable|in:android,ios',
            'device_name' => 'nullable|string|max:255',
        ]);

        $token = UserPushToken::query()->updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
                'device_name' => $validated['device_name']
                    ?? $request->input('device_name')
                    ?? 'mobile-app',
                'last_seen_at' => now(),
            ]
        );

        return $this->mobileSuccess([
            'id' => $token->id,
            'message' => 'Push token registered',
        ]);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:512',
        ]);

        UserPushToken::query()
            ->where('user_id', $request->user()->id)
            ->where('fcm_token', $validated['fcm_token'])
            ->delete();

        return $this->mobileSuccess(['message' => 'Push token removed']);
    }
}
