<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\Device;
use App\Models\Geofence;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Traccar\TraccarGeofenceManager;
use App\Support\Traccar\GeofenceWkt;
use Illuminate\Http\Request;

/**
 * Mobile geofence list + create / update / delete (same backend as web tracking hub).
 */
class GeofenceController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private GeofenceStoreInterface $geofenceStore,
        private GlobalTrackingService $tracking,
        private TraccarGeofenceManager $geofenceManager,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $devices = $this->tracking->devicesForActor($user)->keyBy('id');
        $seen = [];
        $items = collect();

        foreach ($devices as $device) {
            foreach ($this->geofenceStore->forDevice($device) as $geofence) {
                try {
                    $id = (int) $geofence->id;
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;

                    $items->push($this->formatGeofence(
                        $geofence,
                        (int) $device->id,
                        (string) $device->notificationDisplayName(),
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $this->mobileSuccess($items->values());
    }

    public function store(Request $request)
    {
        $validated = $this->validateShape($request);
        $device = $this->resolveAllowedDevice($request, (int) $validated['device_id']);

        try {
            $geofence = $this->geofenceManager->create($device, $validated);
        } catch (\Throwable $e) {
            report($e);

            return $this->mobileError($this->friendlySaveError($e), 422);
        }

        return $this->mobileSuccess($this->formatGeofence(
            $geofence,
            (int) $device->id,
            (string) $device->notificationDisplayName(),
        ), 201);
    }

    public function update(Request $request, int $id)
    {
        $geofence = $this->resolveOwnedGeofence($request, $id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:128',
            'type' => 'sometimes|in:polygon,circle',
            'coords' => 'nullable|array|min:3',
            'coords.*' => 'array|size:2',
            'center' => 'nullable|array|size:2',
            'radius' => 'nullable|numeric|min:1',
            'device_id' => 'sometimes|integer',
        ]);

        if (isset($validated['device_id'])) {
            $device = $this->resolveAllowedDevice($request, (int) $validated['device_id']);
            $geofence->device_id = $device->id;
        }

        try {
            $geofence = $this->geofenceManager->update($geofence, $validated);
        } catch (\Throwable $e) {
            report($e);

            return $this->mobileError($this->friendlySaveError($e), 422);
        }

        $deviceId = (int) ($geofence->device_id ?? $validated['device_id'] ?? 0);
        $device = Device::query()->find($deviceId);

        return $this->mobileSuccess($this->formatGeofence(
            $geofence,
            $deviceId,
            (string) ($device?->notificationDisplayName() ?? ''),
        ));
    }

    public function destroy(Request $request, int $id)
    {
        $geofence = $this->resolveOwnedGeofence($request, $id);
        $this->geofenceManager->delete($geofence);

        return $this->mobileSuccess(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateShape(Request $request): array
    {
        return $request->validate([
            'device_id' => 'required|integer',
            'name' => 'required|string|max:128',
            'type' => 'required|in:polygon,circle',
            'coords' => 'required_if:type,polygon|array|min:3',
            'coords.*' => 'array|size:2',
            'center' => 'required_if:type,circle|array|size:2',
            'radius' => 'required_if:type,circle|numeric|min:1',
        ]);
    }

    private function resolveAllowedDevice(Request $request, int $deviceId): Device
    {
        if (! in_array($deviceId, $this->tracking->filterAllowedIds($request->user(), [$deviceId]), true)) {
            abort(403, 'Device not accessible');
        }

        return Device::query()->findOrFail($deviceId);
    }

    private function resolveOwnedGeofence(Request $request, int $id): Geofence
    {
        $geofence = Geofence::query()->findOrFail($id);
        $deviceId = (int) ($geofence->device_id ?? 0);
        if ($deviceId < 1 || ! in_array($deviceId, $this->tracking->allowedDeviceIds($request->user()), true)) {
            abort(403, 'Geofence not accessible');
        }

        return $geofence;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatGeofence(object $geofence, int $deviceId, string $deviceName): array
    {
        $coords = $this->decodeJsonish($geofence->coords ?? null);
        $center = $this->decodeJsonish($geofence->center ?? null);
        $radius = isset($geofence->radius) ? (int) $geofence->radius : null;
        $type = strtolower((string) ($geofence->type ?? 'polygon'));
        $area = isset($geofence->area) ? (string) $geofence->area : null;

        $shape = GeofenceWkt::resolveShape($type, $coords, $center, $radius, $area);
        $type = $shape['type'];
        $coords = $shape['coords'];
        $center = $shape['center'];
        $radius = $shape['radius'];

        $stroke = $type === 'circle' ? '#2980b9' : '#8e44ad';

        return [
            'id' => (int) $geofence->id,
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'name' => (string) ($geofence->name ?? 'Geofence'),
            'type' => $type,
            'coords' => $coords,
            'center' => $center,
            'radius' => $radius,
            'stroke_color' => $stroke,
            'fill_color' => $stroke,
            'fill_opacity' => 0.12,
        ];
    }

    private function decodeJsonish(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }

    private function friendlySaveError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Data too long')) {
            return 'Geofence shape is too detailed. Use a simpler polygon or a circle.';
        }

        return 'Could not save geofence: '.$msg;
    }
}
