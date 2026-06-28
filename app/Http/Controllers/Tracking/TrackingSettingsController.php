<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\TrackingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingSettingsController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private TrackingSettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.settings', [
            'panel' => $panel,
            'layout' => $this->layoutForPanel($panel),
            'hubRoutes' => $this->trackingHubRoutes($panel),
            'settings' => $this->settings->forActor($request->user()),
            'jsonUrl' => route("{$panel}.tracking.settings.json"),
            'updateUrl' => route("{$panel}.tracking.settings.update"),
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        return $this->noStoreJson([
            'settings' => $this->settings->forActor($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $settings = $this->settings->update($request->user(), $validated['settings']);

        return $this->noStoreJson(['success' => true, 'settings' => $settings]);
    }
}
