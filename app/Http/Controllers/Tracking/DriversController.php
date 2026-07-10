<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\DriverService;
use App\Services\Tracking\GlobalTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriversController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private DriverService $drivers,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.drivers', $this->viewData($request, $panel, [
            'jsonUrl' => route('tracking.drivers.json'),
            'storeUrl' => route('tracking.drivers.store'),
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        return $this->noStoreJson([
            'drivers' => $this->drivers->listForActor($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'unique_id' => 'nullable|string|max:80',
        ]);

        $id = $this->drivers->create($request->user(), $validated);

        return $this->noStoreJson(['success' => $id !== null, 'id' => $id]);
    }

    public function assign(Request $request, int $driver): JsonResponse
    {
        $validated = $request->validate(['device_id' => 'required|integer']);
        $ok = $this->drivers->assign($request->user(), $driver, (int) $validated['device_id']);

        return $this->noStoreJson(['success' => $ok]);
    }

    public function destroy(Request $request, int $driver): JsonResponse
    {
        $ok = $this->drivers->delete($request->user(), $driver);

        return $this->noStoreJson(['success' => $ok]);
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
            'vehicles' => $this->tracking->listItemsForActor($request->user()),
            'hubRoutes' => $this->trackingHubRoutes($panel),
            'trackingUi' => $this->trackingUiFor($request->user()),
        ], $extra);
    }
}
