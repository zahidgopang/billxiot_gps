<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Contracts\Tracking\EventReaderInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\Device;
use App\Models\VehicleEvent;
use App\Models\VehicleEventRead;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlertController extends Controller
{
    use RespondsWithMobileJson;

    private const DEFAULT_HISTORY_DAYS = 90;

    private const MAX_PER_PAGE = 100;

    private const DEFAULT_PER_PAGE = 50;

    private const MAX_DEVICES = 50;

    public function __construct(
        private EventReaderInterface $events,
        private NotificationPreferenceService $notificationPrefs,
        private GlobalTrackingService $tracking,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $devices = $this->tracking->devicesForActor($user);
        $deviceIds = $devices->pluck('id');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query('limit', $request->query('per_page', self::DEFAULT_PER_PAGE))));
        $unreadOnly = $request->boolean('unread_only');

        if ($deviceIds->isEmpty()) {
            return $this->mobileSuccess($this->paginatedPayload([], $page, $perPage, 0));
        }

        $devicesById = $devices->keyBy('id');
        $readIds = $this->readEventIds($user->id);

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(self::DEFAULT_HISTORY_DAYS);
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : null;

        $events = $this->collectFleetEvents($deviceIds, $devicesById, $from, $to, $request);
        $events = $this->applyWebPreferences($user, $events, $request);

        if ($unreadOnly) {
            $events = $events
                ->filter(fn (VehicleEvent $e) => ! in_array($e->id, $readIds, true))
                ->values();
        }

        $total = $events->count();
        $slice = $events->slice(($page - 1) * $perPage, $perPage)->values();

        $alerts = $slice
            ->map(fn (VehicleEvent $e) => $this->formatAlert($e, $readIds, $devicesById))
            ->values()
            ->all();

        return $this->mobileSuccess($this->paginatedPayload($alerts, $page, $perPage, $total));
    }

    public function unread(Request $request)
    {
        $user = $request->user();
        $devices = $this->tracking->devicesForActor($user);
        $deviceIds = $devices->pluck('id');

        if ($deviceIds->isEmpty()) {
            return $this->mobileSuccess(['count' => 0, 'alerts' => []]);
        }

        $devicesById = $devices->keyBy('id');
        $readIds = $this->readEventIds($user->id);

        $events = $this->applyWebPreferences(
            $user,
            $this->collectFleetEvents(
                $deviceIds,
                $devicesById,
                now()->subDays(self::DEFAULT_HISTORY_DAYS),
                null,
                $request
            ),
            $request
        )->filter(fn (VehicleEvent $e) => ! in_array($e->id, $readIds, true));

        $previewLimit = min(self::MAX_PER_PAGE, max(1, (int) $request->query('limit', 50)));

        return $this->mobileSuccess([
            'count' => $events->count(),
            'alerts' => $events
                ->take($previewLimit)
                ->map(fn (VehicleEvent $e) => $this->formatAlert($e, $readIds, $devicesById))
                ->values(),
        ]);
    }

    public function markRead(Request $request)
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
        ]);

        $user = $request->user();

        $validIds = collect($validated['alert_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $now = now();
        $rows = $validIds->map(fn ($id) => [
            'user_id' => $user->id,
            'vehicle_event_id' => $id,
            'read_at' => $now,
        ])->all();

        if ($rows !== [] && Schema::hasTable('vehicle_event_reads')) {
            DB::table('vehicle_event_reads')->upsert(
                $rows,
                ['user_id', 'vehicle_event_id'],
                ['read_at']
            );
        }

        return $this->mobileSuccess([
            'marked' => count($rows),
            'message' => 'Alerts marked as read',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     * @return array<string, mixed>
     */
    private function paginatedPayload(array $alerts, int $page, int $perPage, int $total): array
    {
        return [
            'alerts' => $alerts,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
        ];
    }

    /**
     * @param  Collection<int, int>|Collection<int, mixed>  $deviceIds
     * @param  Collection<int, Device>  $devicesById
     * @return Collection<int, VehicleEvent>
     */
    private function collectFleetEvents(
        $deviceIds,
        $devicesById,
        Carbon $from,
        ?Carbon $to,
        Request $request,
    ): Collection {
        $allowed = $devicesById;
        if ($request->filled('device_id')) {
            $device = $devicesById->get((int) $request->device_id);
            $allowed = $device ? collect([(int) $device->id => $device]) : collect();
        } else {
            $allowed = $devicesById->take(self::MAX_DEVICES);
        }

        if ($allowed->isEmpty()) {
            return collect();
        }

        $types = $request->filled('type') ? [(string) $request->input('type')] : null;
        $perDeviceLimit = min(200, max(40, (int) ceil(400 / max(1, $allowed->count()))));

        $merged = collect();
        foreach ($allowed as $device) {
            $merged = $merged->merge(
                $this->events->forDevice($device, $from, $to, $types, limit: $perDeviceLimit)
            );
        }

        return $merged
            ->sortByDesc(fn (VehicleEvent $e) => $e->occurred_at?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * @param  Collection<int, VehicleEvent>  $events
     * @return Collection<int, VehicleEvent>
     */
    private function applyWebPreferences($user, $events, Request $request)
    {
        if ($request->filled('type')) {
            return $events;
        }

        $suppressed = $this->notificationPrefs->suppressedWebTypes($user);
        if ($suppressed === []) {
            return $events;
        }

        return $events
            ->filter(fn (VehicleEvent $e) => ! $this->notificationPrefs->isWebSuppressed($user, $e->type))
            ->values();
    }

    /**
     * @param  Collection<int, Device>  $devicesById
     * @param  list<int>  $readIds
     * @return array<string, mixed>
     */
    private function formatAlert(VehicleEvent $event, array $readIds, $devicesById): array
    {
        $device = $devicesById->get($event->device_id) ?? $event->device;

        return array_merge($event->toAlertArray(), [
            'device_id' => $event->device_id,
            'device_name' => $device?->notificationDisplayName(),
            'vehicle_name' => $device?->vehicle_name,
            'vehicle_number' => $device?->vehicle_number,
            'read' => in_array($event->id, $readIds, true),
        ]);
    }

    /**
     * @return list<int>
     */
    private function readEventIds(int $userId): array
    {
        if (! Schema::hasTable('vehicle_event_reads')) {
            return [];
        }

        return VehicleEventRead::query()
            ->where('user_id', $userId)
            ->pluck('vehicle_event_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
