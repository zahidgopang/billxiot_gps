<?php

namespace App\Http\Controllers\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\VehicleEventRead;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class TrackingEventsController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private EventReaderInterface $events,
        private NotificationPreferenceService $notificationPrefs,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.events', $this->viewData($request, $panel, [
            'jsonUrl' => route("{$panel}.tracking.events.json"),
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = $this->parseTrackingIdList($request);
        $allowed = $ids === [] ? $this->tracking->allowedDeviceIds($user) : $this->tracking->filterAllowedIds($user, $ids);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));

        if ($request->boolean('alert_poll')) {
            return $this->alertPollJson($request, $user, $allowed, $page, $perPage);
        }

        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->subDays(7);
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : now();
        $type = $request->query('type');
        $types = $type ? [(string) $type] : null;

        // Respect the user's per-type "web" notification preferences (unless filtering by type).
        $all = [];
        foreach (array_slice($allowed, 0, 20) as $deviceId) {
            $device = Device::query()->find($deviceId);
            if (! $device) {
                continue;
            }
            foreach ($this->events->forDevice($device, $from, $to, $types, limit: 200) as $event) {
                if (! $type && $this->notificationPrefs->isWebSuppressed($user, $event->type)) {
                    continue;
                }
                $all[] = array_merge($event->toAlertArray(), [
                    'device_id' => $device->id,
                    'device_name' => $device->mapMarkerTitle(),
                    'lat' => $event->lat,
                    'lng' => $event->lng,
                ]);
            }
        }

        usort($all, fn ($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));
        $total = count($all);
        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);

        return $this->noStoreJson([
            'events' => $slice,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    private function alertPollJson(Request $request, $user, array $allowed, int $page, int $perPage): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after_id', 0));
        $deviceIds = array_slice($allowed, 0, 50);
        $cacheSeconds = 10;
        $cacheKey = 'tracking.events.alert.'
            . $user->id
            . '.'
            . $afterId
            . '.'
            . md5(implode(',', $deviceIds));

        $payload = Cache::remember($cacheKey, $cacheSeconds, function () use ($user, $deviceIds, $afterId, $perPage, $page) {
            $all = [];

            if ($afterId > 0) {
                foreach ($deviceIds as $deviceId) {
                    $device = Device::query()->find($deviceId);
                    if (! $device) {
                        continue;
                    }
                    foreach ($this->events->afterIdForDevice($device, $afterId, 8) as $event) {
                        if ($this->notificationPrefs->isWebSuppressed($user, $event->type)) {
                            continue;
                        }
                        $all[] = array_merge($event->toAlertArray(), [
                            'device_id' => $device->id,
                            'device_name' => $device->mapMarkerTitle(),
                            'lat' => $event->lat,
                            'lng' => $event->lng,
                        ]);
                    }
                }
            } else {
                $recent = $this->events->recentForDevices(collect($deviceIds), max($perPage, 25));
                foreach ($recent as $event) {
                    $deviceId = (int) ($event->device_id ?? 0);
                    if ($deviceId < 1) {
                        continue;
                    }
                    if ($this->notificationPrefs->isWebSuppressed($user, $event->type)) {
                        continue;
                    }
                    $device = Device::query()->find($deviceId);
                    $all[] = array_merge($event->toAlertArray(), [
                        'device_id' => $deviceId,
                        'device_name' => $device?->mapMarkerTitle() ?? ('#'.$deviceId),
                        'lat' => $event->lat,
                        'lng' => $event->lng,
                    ]);
                }
            }

            usort($all, fn ($a, $b) => ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0)));

            $total = count($all);
            $slice = array_slice($all, ($page - 1) * $perPage, $perPage);

            return [
                'events' => $slice,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ];
        });

        return $this->noStoreJson($payload);
    }

    public function markRead(Request $request, int $eventId): JsonResponse
    {
        VehicleEventRead::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'vehicle_event_id' => $eventId],
            ['read_at' => now()]
        );

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
            'vehicles' => $this->tracking->listItemsForActor($request->user()),
            'hubRoutes' => $this->trackingHubRoutes($panel),
        ], $extra);
    }
}
