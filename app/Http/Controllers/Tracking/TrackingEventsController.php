<?php

namespace App\Http\Controllers\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\VehicleEventRead;
use App\Services\Tracking\GlobalTrackingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingEventsController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private EventReaderInterface $events,
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
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->subDays(7);
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : now();
        $type = $request->query('type');
        $types = $type ? [(string) $type] : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));

        $all = [];
        foreach (array_slice($allowed, 0, 20) as $deviceId) {
            $device = Device::query()->find($deviceId);
            if (! $device) {
                continue;
            }
            foreach ($this->events->forDevice($device, $from, $to, $types, limit: 200) as $event) {
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
