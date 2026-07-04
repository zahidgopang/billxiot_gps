<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Tracking\NotificationPreferenceService;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private NotificationPreferenceService $notifications,
    ) {}

    public function show(Request $request)
    {
        return $this->mobileSuccess([
            'preferences' => $this->notifications->preferencesForUser($request->user()),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.type' => 'required|string',
            'preferences.*.web' => 'boolean',
            'preferences.*.push' => 'boolean',
            'preferences.*.email' => 'boolean',
            'preferences.*.whatsapp' => 'boolean',
        ]);

        $this->notifications->update($request->user(), $validated['preferences']);

        return $this->mobileSuccess([
            'preferences' => $this->notifications->preferencesForUser($request->user()),
            'message' => (string) __('app.tracking.notif_saved'),
        ]);
    }
}
