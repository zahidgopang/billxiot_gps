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
use App\Services\Mobile\MobileRouteAnalyticsService;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\Tracking\DeviceMapHistoryService;
use App\Services\Tracking\NotificationPreferenceService;
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
        private DeviceMapHistoryService $mapHistory,
        private MobileRouteAnalyticsService $routeAnalytics,
        private NotificationPreferenceService $notificationPrefs,
    ) {}

    public function map(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);

        $latestLocation = $this->positions->latestForDevice($device);
        $initialPoint = array_merge(
            $this->formatLocationForDevice($latestLocation, $device) ?? [],
            ['route_trip' => $this->routeTripPayload($device, $latestLocation)['route_trip'] ?? null],
        );
        if ($initialPoint === []) {
            $initialPoint = null;
        }

        $isAdminMap = $this->isAdminMapRequest();
        $mapApiRoutes = $this->mapApiRoutes($device, $token);
        $mapToken = $token;

        if ($isAdminMap) {
            $device->loadMissing('user');
        }

        $user = $request->user();
        $mapTourMode = $user->getMapTourPreference();
        $showMapTourOnLoad = $user->shouldShowMapTourOnLoad();
        $initialAlerts = $this->filterByWebPrefs($this->bellAlertsForDevice($device, 15), $user)
            ->map(fn (VehicleEvent $event) => $event->toAlertArray())
            ->values()
            ->all();

        $initialStatus = $latestLocation
            ? $this->mapStatus->resolve($latestLocation, $device)
            : ['label' => __('app.common.offline'), 'key' => 'offline'];

        $canManageGeofences = $this->canManageGeofencesOnMap($device);

        return view('user.device-map', compact(
            'device',
            'latestLocation',
            'initialPoint',
            'initialStatus',
            'initialAlerts',
            'isAdminMap',
            'canManageGeofences',
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
        if (! $location) {
            return null;
        }

        return array_merge(
            \App\Support\Tracking\DeviceLocationPayload::fromDeviceLocation($location, $device),
            $device->mapAppearancePayload(),
            [
                'gps_fix' => $location->gps_fix,
                'time' => AppDateTime::toApi($location->recorded_at),
                'vehicle_name' => $device->vehicle_name,
                'vehicle_number' => $device->vehicle_number,
                'driver_name' => $device->driverDisplayName(),
                'driver_contact' => $device->driverContactNumber(),
                'map_marker_title' => $device->mapMarkerTitle(),
                'map_marker_plate' => $device->mapMarkerPlateLine(),
            ],
        );
    }

    public function historyJson(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);
        $context = $this->resolveMapHistoryContext($request);
        $fetch = $this->mapHistory->fetchLocations(
            $device,
            $context['range']['from'],
            $context['range']['to'],
            $context['explicit_range'],
        );

        $bundle = $this->mapHistory->buildFullPayload($fetch['locations']);

        $payload = array_merge($bundle, [
            'used_fallback' => $fetch['used_fallback'],
            'history_fallback' => $fetch['used_fallback'] ? $fetch['fallback_reason'] : null,
        ]);

        return $this->historyJsonResponse($request, $payload, $fetch, count($payload['points']));
    }

    /**
     * Fast map polyline payload — downsampled points only (parallel with analytics).
     */
    public function historyPointsJson(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);
        $context = $this->resolveMapHistoryContext($request);
        $fetch = $this->mapHistory->fetchLocations(
            $device,
            $context['range']['from'],
            $context['range']['to'],
            $context['explicit_range'],
        );

        $total = $fetch['locations']->count();
        $mapLocations = $this->mapHistory->downsampleForMap($fetch['locations']);

        $payload = [
            'points' => $this->mapHistory->formatMapPoints($mapLocations),
            'point_count' => $total,
            'map_point_count' => $mapLocations->count(),
            'used_fallback' => $fetch['used_fallback'],
            'history_fallback' => $fetch['used_fallback'] ? $fetch['fallback_reason'] : null,
        ];

        return $this->historyJsonResponse($request, $payload, $fetch, $total);
    }

    /**
     * Trip summary, timeline, stops, statistics (parallel with points).
     */
    public function historyAnalyticsJson(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);
        $context = $this->resolveMapHistoryContext($request);
        $fetch = $this->mapHistory->fetchLocations(
            $device,
            $context['range']['from'],
            $context['range']['to'],
            $context['explicit_range'],
        );

        $analytics = $this->mapHistory->analyzeForMap($fetch['locations']);

        $payload = array_merge($analytics, [
            'point_count' => $fetch['locations']->count(),
            'used_fallback' => $fetch['used_fallback'],
            'history_fallback' => $fetch['used_fallback'] ? $fetch['fallback_reason'] : null,
        ]);

        return $this->historyJsonResponse($request, $payload, $fetch, $fetch['locations']->count());
    }

    /**
     * @return array{range: array{from: \Carbon\Carbon, to: ?\Carbon\Carbon}, explicit_range: bool}
     */
    private function resolveMapHistoryContext(Request $request): array
    {
        $range = $this->resolveHistoryRange($request);
        $explicitRange = trim((string) ($request->query('from', $request->input('from', '')))) !== '';

        return [
            'range' => $range,
            'explicit_range' => $explicitRange,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{used_fallback: bool, fallback_reason: ?string}  $fetch
     */
    private function historyJsonResponse(Request $request, array $payload, array $fetch, int $pointCount)
    {
        $response = response()->json($payload);

        if ($request->boolean('debug_gps') || $request->query('debug_gps') === '1') {
            $response->header('X-History-Count', (string) $pointCount);
            $response->header('X-History-From', (string) $request->query('from', ''));
            $response->header('X-History-To', (string) $request->query('to', ''));
            $range = $this->resolveHistoryRange($request);
            if ($range['to'] !== null) {
                $response->header('X-History-From-Bound', $range['from']->toIso8601String());
                $response->header('X-History-To-Bound', $range['to']->toIso8601String());
            }
        }

        if ($fetch['used_fallback'] && $fetch['fallback_reason']) {
            $response->header('X-History-Fallback', $fetch['fallback_reason']);
        }

        return $response;
    }

    public function liveJson(string $token)
    {
        $device = $this->findMapDevice($token);

        $latest = $this->positions->latestForDevice($device);

        return response()->json(array_merge(
            $this->formatLocationForDevice($latest, $device) ?? [],
            ['route_trip' => $this->routeTripPayload($device, $latest)['route_trip'] ?? null],
        ))->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function completeTrip(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);

        try {
            $trip = app(\App\Services\Routes\TripManagementService::class)
                ->markCompleted($device, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $latest = $this->positions->latestForDevice($device);

        return response()->json([
            'success' => true,
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'message' => __('app.routes.trip_marked_completed'),
            'route_trip' => $this->routeTripPayload($device, $latest)['route_trip'] ?? null,
        ]);
    }

    public function startNewTrip(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);
        $latest = $this->positions->latestForDevice($device);

        try {
            $trip = app(\App\Services\Routes\TripManagementService::class)->startNewTrip(
                $device,
                $latest ? (float) $latest->lat : null,
                $latest ? (float) $latest->lng : null,
                $latest ? (float) ($latest->speed ?? 0) : null,
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $message = $trip->started_at
            ? __('app.routes.trip_started')
            : __('app.routes.trip_armed_waiting_start');

        return response()->json([
            'success' => true,
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'message' => $message,
            'route_trip' => $this->routeTripPayload($device, $latest)['route_trip'] ?? null,
        ]);
    }

    public function restartTrip(Request $request, string $token)
    {
        $device = $this->findMapDevice($token);
        $latest = $this->positions->latestForDevice($device);

        try {
            $trip = app(\App\Services\Routes\TripManagementService::class)->restartTrip(
                $device,
                $latest ? (float) $latest->lat : null,
                $latest ? (float) $latest->lng : null,
                $latest ? (float) ($latest->speed ?? 0) : null,
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $message = $trip->started_at
            ? __('app.routes.trip_restarted')
            : __('app.routes.trip_armed_waiting_start');

        return response()->json([
            'success' => true,
            'trip' => [
                'id' => $trip->id,
                'status' => $trip->status,
            ],
            'message' => $message,
            'route_trip' => $this->routeTripPayload($device, $latest)['route_trip'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function routeTripPayload(Device $device, ?DeviceLocation $location): array
    {
        $service = app(\App\Services\Routes\TripManagementService::class);
        $payload = $service->payloadForDevice(
            $device,
            $location ? (float) $location->lat : null,
            $location ? (float) $location->lng : null,
            $location ? (float) ($location->speed ?? 0) : null,
            $location ? (float) ($location->heading ?? 0) : null,
            app(\App\Services\Mobile\MobileMapStatusResolver::class)
                ->resolve($location, $device)['connectivity_tier'] === 'live',
        );

        return $payload ? ['route_trip' => $payload] : [];
    }

    public function summaryJson(string $token)
    {
        $device = $this->findMapDevice($token);

        $latest = $this->positions->latestForDevice($device);

        $formatted = $this->formatLocation($latest);

        return response()->json(array_merge([
            'imei' => $device->imei,
            'name' => $device->name,
            'online' => $this->mapStatus->isRecentlyOnline($latest),
            'last_seen' => $latest?->recorded_at?->diffForHumans() ?? 'No data',
        ], $formatted ?? []))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
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

        $events = $this->filterByWebPrefs($collection, $request->user())
            ->map(fn (VehicleEvent $event) => $event->toAlertArray())
            ->values();

        return response()->json($events);
    }

    /**
     * Drop event types the viewing user disabled for the web notification feed.
     * No-op for guest/token viewers without preferences.
     */
    private function filterByWebPrefs(Collection $events, $user): Collection
    {
        if (! $user) {
            return $events;
        }

        return $events
            ->filter(fn (VehicleEvent $event) => ! $this->notificationPrefs->isWebSuppressed($user, $event->type))
            ->values();
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
