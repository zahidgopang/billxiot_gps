<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingNotificationsController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private NotificationPreferenceService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.notifications', $this->viewData($request, $panel, [
            'jsonUrl' => route("{$panel}.tracking.notifications.json"),
            'updateUrl' => route("{$panel}.tracking.notifications.update"),
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        return $this->noStoreJson([
            'preferences' => $this->notifications->preferencesForUser($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.type' => 'required|string',
            'preferences.*.web' => 'boolean',
            'preferences.*.push' => 'boolean',
        ]);

        $this->notifications->update($request->user(), $validated['preferences']);

        return $this->noStoreJson(['success' => true]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function viewData(Request $request, string $panel, array $extra = []): array
    {
        return array_merge([
            'panel' => $panel,
            'layout' => $this->layoutForPanel($panel),
            'hubRoutes' => $this->trackingHubRoutes($panel),
        ], $extra);
    }
}
