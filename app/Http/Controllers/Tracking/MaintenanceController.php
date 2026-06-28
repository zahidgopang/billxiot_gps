<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private MaintenanceService $maintenance,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.maintenance', $this->viewData($request, $panel, [
            'jsonUrl' => route("{$panel}.tracking.maintenance.json"),
            'storeUrl' => route("{$panel}.tracking.maintenance.store"),
            'baseUrl' => route("{$panel}.tracking.maintenance.index"),
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        return $this->noStoreJson([
            'items' => $this->maintenance->listForActor($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $id = $this->maintenance->save($request->user(), $this->validateMaintenance($request));

        return $this->noStoreJson(['success' => $id !== null, 'id' => $id]);
    }

    public function update(Request $request, int $maintenance): JsonResponse
    {
        $id = $this->maintenance->save($request->user(), $this->validateMaintenance($request), $maintenance);

        return $this->noStoreJson(['success' => $id !== null, 'id' => $id]);
    }

    public function destroy(Request $request, int $maintenance): JsonResponse
    {
        $ok = $this->maintenance->delete($request->user(), $maintenance);

        return $this->noStoreJson(['success' => $ok]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMaintenance(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:160',
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'integer',
            'data_list' => 'nullable|boolean',
            'popup' => 'nullable|boolean',
            'odometer_enabled' => 'nullable|boolean',
            'odometer_interval' => 'nullable|numeric|min:0',
            'odometer_last' => 'nullable|numeric|min:0',
            'hours_enabled' => 'nullable|boolean',
            'hours_interval' => 'nullable|numeric|min:0',
            'hours_last' => 'nullable|numeric|min:0',
            'days_enabled' => 'nullable|boolean',
            'days_interval' => 'nullable|numeric|min:0',
            'days_last' => 'nullable|date',
            'trigger_odometer' => 'nullable|boolean',
            'trigger_hours' => 'nullable|boolean',
            'trigger_days' => 'nullable|boolean',
            'update_last_service' => 'nullable|boolean',
        ]);
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
        ], $extra);
    }
}
