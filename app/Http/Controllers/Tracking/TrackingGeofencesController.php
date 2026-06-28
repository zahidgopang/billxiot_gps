<?php

namespace App\Http\Controllers\Tracking;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Geofence;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Traccar\TraccarGeofenceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingGeofencesController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private TraccarGeofenceManager $geofenceManager,
        private GeofenceStoreInterface $geofenceStore,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.geofences', $this->viewData($request, $panel, [
            'jsonUrl' => route("{$panel}.tracking.geofences.json"),
            'storeUrl' => route("{$panel}.tracking.geofences.store"),
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        $devices = $this->tracking->devicesForActor($request->user());
        $items = [];

        foreach ($devices as $device) {
            foreach ($this->geofenceStore->forDevice($device) as $geofence) {
                $items[] = [
                    'id' => (int) $geofence->id,
                    'device_id' => $device->id,
                    'device_name' => $device->mapMarkerTitle(),
                    'name' => (string) $geofence->name,
                    'type' => (string) $geofence->type,
                    'coords' => $geofence->coords,
                    'center' => $geofence->center,
                    'radius' => $geofence->radius,
                    'color' => $geofence->color ?? null,
                ];
            }
        }

        return $this->noStoreJson(['geofences' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'name' => 'required|string|max:120',
            'type' => 'required|in:polygon,circle',
            'coords' => 'nullable|array',
            'center' => 'nullable|array',
            'radius' => 'nullable|numeric',
        ]);

        $deviceId = (int) $validated['device_id'];
        if (! in_array($deviceId, $this->tracking->filterAllowedIds($request->user(), [$deviceId]), true)) {
            return $this->noStoreJson(['success' => false, 'message' => 'Device not accessible'], 403);
        }

        $device = Device::query()->findOrFail($deviceId);
        $geofence = $this->geofenceManager->create($device, $validated);

        return $this->noStoreJson(['success' => true, 'id' => $geofence->id]);
    }

    public function update(Request $request, Geofence $geofence): JsonResponse
    {
        $this->authorizeGeofence($request, $geofence);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'type' => 'sometimes|in:polygon,circle',
            'coords' => 'nullable|array',
            'center' => 'nullable|array',
            'radius' => 'nullable|numeric',
        ]);

        $this->geofenceManager->update($geofence, $validated);

        return $this->noStoreJson(['success' => true]);
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        $this->authorizeGeofence($request, $geofence);
        $this->geofenceManager->delete($geofence);

        return $this->noStoreJson(['success' => true]);
    }

    private function authorizeGeofence(Request $request, Geofence $geofence): void
    {
        $deviceId = $geofence->device_id;
        if (! $deviceId || ! in_array((int) $deviceId, $this->tracking->allowedDeviceIds($request->user()), true)) {
            abort(403);
        }
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
            'googleMapsKey' => config('services.google.maps_key'),
        ], $extra);
    }
}
