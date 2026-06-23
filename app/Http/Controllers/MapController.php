<?php

namespace App\Http\Controllers;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Contracts\Tracking\EventReaderInterface;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Http\Concerns\ResolvesMapDevice;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\VehicleEvent;
use App\Support\DateTime\AppDateTime;
use App\Services\Mobile\MobileMapStatusResolver;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\VehicleEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    use \App\Http\Concerns\ResolvesHistoryDateRange;
    use ResolvesMapDevice;

    public function __construct(
        private PositionReaderInterface $positions,
        private EventReaderInterface $events,
        private GeofenceStoreInterface $geofences,
        private MobileMapStatusResolver $mapStatus,
        private DeviceHistoryFetcher $historyFetcher,
    ) {}

    public function map(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);

        $range = $this->resolveHistoryRange($request);

        $locations = $this->positions->historyForDevice(
            $device,
            $range['from'],
            $range['to'],
            'asc'
        );

        $latestLocation = $this->positions->latestForDevice($device);
        $initialPoint = $this->formatLocationForDevice($latestLocation, $device);

        $isAdminMap = $this->isAdminMapRequest();
        $mapApiRoutes = $this->mapApiRoutes($device, $token);
        $mapToken = $token;

        if ($isAdminMap) {
            $device->loadMissing('user');
        }

        $user = $request->user();
        $mapTourMode = $user->getMapTourPreference();
        $showMapTourOnLoad = $user->shouldShowMapTourOnLoad();
        $initialAlerts = $this->bellAlertsForDevice($device, 15)
            ->map(fn (VehicleEvent $event) => $event->toAlertArray())
            ->values()
            ->all();

        $initialStatus = $latestLocation
            ? $this->mapStatus->resolve($latestLocation, $device)
            : ['label' => __('app.common.offline'), 'key' => 'offline'];

        return view('user.device-map', compact(
            'device',
            'locations',
            'latestLocation',
            'initialPoint',
            'initialStatus',
            'initialAlerts',
            'isAdminMap',
            'mapApiRoutes',
            'mapToken',
            'mapTourMode',
            'showMapTourOnLoad'
        ));
    }

    private function formatLocation(?DeviceLocation $location): ?array
    {
        if (! $location) {
            return null;
        }

        return [
            'lat' => (float) $location->lat,
            'lng' => (float) $location->lng,
            'speed' => (float) ($location->speed ?? 0),
            'heading' => (float) ($location->heading ?? 0),
            'battery' => $location->battery_level,
            'battery_level' => $location->battery_level,
            'ignition' => (bool) $location->ignition,
            'acc' => (bool) ($location->acc ?? false),
            'gsm_signal' => $location->gsm_signal,
            'gps_signal' => $location->gps_signal,
            'satellites' => $location->satellites,
            'odometer' => $location->odometer,
            'power_cut' => (bool) $location->power_cut,
            'panic' => (bool) $location->panic,
            'gps_fix' => $location->gps_fix,
            'recorded_at' => AppDateTime::toApi($location->recorded_at),
            'time' => AppDateTime::toApi($location->recorded_at),
            'timestamp' => $location->recorded_at
                ? AppDateTime::format($location->recorded_at, 'log')
                : null,
            'position_id' => (int) ($location->id ?? 0),
        ];
    }

    /**
     * Live map payload with status aligned to MobileMapStatusResolver / mobile app.
     *
     * @return array<string, mixed>|null
     */
    private function formatLocationForDevice(?DeviceLocation $location, Device $device): ?array
    {
        $formatted = $this->formatLocation($location);

        if (! $formatted) {
            return null;
        }

        $map = $this->mapStatus->resolve($location, $device);

        return array_merge($formatted, [
            'status' => $map['label'],
            'status_key' => $map['key'],
            'connectivity_tier' => $map['connectivity_tier'],
            'last_known_status' => $map['last_known_status'],
            'last_known_status_key' => $map['last_known_status_key'],
            'last_known_speed' => $map['last_known_speed'],
            'last_known_ignition' => $map['last_known_ignition'],
            'is_online' => $this->mapStatus->isRecentlyOnline($location),
            'online' => $this->mapStatus->isRecentlyOnline($location),
            'vehicle_name' => $device->vehicle_name,
            'vehicle_number' => $device->vehicle_number,
            'map_marker_title' => $device->mapMarkerTitle(),
            'map_marker_plate' => $device->mapMarkerPlateLine(),
        ]);
    }

    public function historyJson(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);

        $range = $this->resolveHistoryRange($request);
        $result = $this->fetchDeviceHistoryPoints($device, $request, $range);

        $response = response()->json($result['points']);

        if ($request->boolean('debug_gps') || $request->query('debug_gps') === '1') {
            $response->header('X-History-Count', (string) count($result['points']));
            $response->header('X-History-From', (string) $request->query('from', ''));
            $response->header('X-History-To', (string) $request->query('to', ''));
            if ($range['to'] !== null) {
                $response->header('X-History-From-Bound', $range['from']->toIso8601String());
                $response->header('X-History-To-Bound', $range['to']->toIso8601String());
            }
        }

        if ($result['used_fallback'] && $result['fallback_reason']) {
            $response->header('X-History-Fallback', $result['fallback_reason']);
        }

        return $response;
    }

    /**
     * Default (no dates) → last 24 hours from now. If empty, load last known activity.
     *
     * @return array{points: array<int, array>, used_fallback: bool, fallback_reason: ?string}
     */
    private function fetchDeviceHistoryPoints(Device $device, Request $request, ?array $range = null): array
    {
        $range ??= $this->resolveHistoryRange($request);
        $explicitRange = trim((string) ($request->query('from', $request->input('from', '')))) !== '';

        $result = $this->historyFetcher->fetch(
            $device,
            $range['from'],
            $range['to'],
            $explicitRange
        );

        return [
            'points' => $this->formatLocationsCollection($result['locations']),
            'used_fallback' => $result['used_fallback'],
            'fallback_reason' => $result['fallback_reason'],
        ];
    }

    /**
     * @return array<int, array>
     */
    private function formatLocationsCollection(Collection $locations): array
    {
        return $locations
            ->map(fn ($loc) => $this->formatLocation($loc))
            ->filter()
            ->values()
            ->all();
    }

    public function liveJson(string $token)
    {
        $device = $this->findMapDevice($token);

        $latest = $this->positions->latestForDevice($device);

        if ($latest && config('tracking.laravel_geofence_detection', true)) {
            app(VehicleEventService::class)->processGeofenceFromLocation(
                $device,
                (float) $latest->lat,
                (float) $latest->lng,
                $latest->recorded_at ?? now()
            );
        }

        return response()->json($this->formatLocationForDevice($latest, $device) ?? []);
    }

    public function summaryJson(string $token)
    {
        $device = $this->findMapDevice($token);

        $latest = $this->positions->latestForDevice($device);

        $formatted = $this->formatLocation($latest);

        return response()->json(array_merge([
            'imei' => $device->imei,
            'name' => $device->name,
            'online' => $latest && $latest->recorded_at >= now()->subMinutes(5),
            'last_seen' => $latest?->recorded_at?->diffForHumans() ?? 'No data',
        ], $formatted ?? []));
    }

    public function alertsJson(string $token, Request $request)
    {
        $device = $this->findMapDevice($token);

        $limit = min(30, max(1, (int) $request->query('limit', 8)));
        $afterId = (int) $request->query('after_id', 0);
        $bell = $request->boolean('bell', false);

        if ($afterId > 0) {
            $collection = $this->events->afterIdForDevice($device, $afterId, $limit);
        } elseif ($bell) {
            $collection = $this->bellAlertsForDevice($device, $limit);
        } else {
            $collection = $this->events->latestForDevice($device, $limit);
        }

        $events = $collection
            ->map(fn (VehicleEvent $event) => $event->toAlertArray())
            ->values();

        return response()->json($events);
    }

    /**
     * Map bell: show geofence enter/exit from tc_events (primary), then other safety alerts.
     */
    private function bellAlertsForDevice(Device $device, int $limit): Collection
    {
        $geofence = $this->events->forDevice($device, types: [
            VehicleEvent::TYPE_GEOFENCE_ENTER,
            VehicleEvent::TYPE_GEOFENCE_EXIT,
        ], limit: max($limit, 12));

        if ($geofence->isNotEmpty()) {
            return $geofence->take($limit)->values();
        }

        $rows = $this->events->latestForDevice($device, min(40, $limit * 3));

        $priority = static function (VehicleEvent $event): int {
            return match ($event->type) {
                VehicleEvent::TYPE_PANIC,
                VehicleEvent::TYPE_POWER_CUT,
                VehicleEvent::TYPE_COMM_LOST_MOVING,
                VehicleEvent::TYPE_TAMPERING => 95,
                VehicleEvent::TYPE_OVERSPEED,
                VehicleEvent::TYPE_IGNITION => 80,
                VehicleEvent::TYPE_COMM_LOST_IGNITION,
                VehicleEvent::TYPE_OFFLINE => 75,
                VehicleEvent::TYPE_LOW_BATTERY,
                VehicleEvent::TYPE_GSM_WEAK,
                VehicleEvent::TYPE_GPS_WEAK => 70,
                VehicleEvent::TYPE_DELAYED => 60,
                default => 10,
            };
        };

        return $rows
            ->sortByDesc(fn (VehicleEvent $event) => ($priority($event) * 1_000_000_000) + (int) $event->id)
            ->take($limit)
            ->values();
    }

    public function reverseGeocode(Request $request, string $token)
    {
        $this->findMapDevice($token);

        $lat = $request->lat;
        $lng = $request->lng;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['error' => 'Invalid lat/lng'], 400);
        }

        $response = Http::withHeaders([
            'User-Agent' => 'YourAppName/1.0 (contact@yourdomain.com)',
        ])->timeout(5)->get('https://nominatim.openstreetmap.org/reverse', [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
        ]);

        if (
            $response->successful() &&
            ! empty($response->json('display_name'))
        ) {
            return response()->json([
                'address' => $response->json('display_name'),
            ]);
        }

        return $this->reverseGeocodeGoogle($lat, $lng);
    }

    public function reverseGeocodeGoogle($lat, $lng)
    {
        $apiKey = config('services.google.maps_key');

        if (! $apiKey) {
            return response()->json(['address' => 'API key missing']);
        }

        $response = Http::timeout(5)->get(
            'https://maps.googleapis.com/maps/api/geocode/json',
            [
                'latlng' => "$lat,$lng",
                'key' => $apiKey,
            ]
        );

        if ($response->successful()) {
            $data = $response->json();

            if ($data['status'] === 'OK' && ! empty($data['results'])) {
                return response()->json([
                    'address' => $data['results'][0]['formatted_address'],
                ]);
            }
        }

        return response()->json(['address' => 'Not found']);
    }

    public function geofencesJson(string $token)
    {
        $device = $this->findMapDevice($token);

        $formatted = $this->geofences->forDevice($device)->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'type' => $g->type,
            'coords' => $g->coords,
            'center' => $g->center,
            'radius' => $g->radius,
        ]);

        return response()->json($formatted);
    }
}
