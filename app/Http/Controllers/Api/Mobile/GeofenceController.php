<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Support\Traccar\GeofenceWkt;
use Illuminate\Http\Request;

/**
 * Mobile geofences are read-only. Create/edit/delete from web/admin only.
 */
class GeofenceController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private GeofenceStoreInterface $geofenceStore,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $devices = $user->trackableDevicesQuery()->get()->keyBy('id');
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
        $fill = $stroke;

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
            'fill_color' => $fill,
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
}
