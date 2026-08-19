<?php

namespace App\Http\Controllers\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Models\VehicleEventRead;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TrackingNotificationsController extends Controller
{
    use ResolvesTrackingPanel;

    private const DEFAULT_HISTORY_DAYS = 90;

    private const MAX_PER_PAGE = 100;

    private const MAX_DEVICES = 50;

    public function __construct(
        private GlobalTrackingService $tracking,
        private NotificationPreferenceService $notifications,
        private EventReaderInterface $events,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.notifications', $this->viewData($request, $panel, [
            'jsonUrl' => route('tracking.notifications.json'),
            'updateUrl' => route('tracking.notifications.update'),
            'inboxUrl' => route('tracking.notifications.inbox'),
            'markReadUrl' => route('tracking.notifications.read'),
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        return $this->noStoreJson([
            'preferences' => $this->notifications->preferencesForUser($request->user()),
        ]);
    }

    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        $devices = $this->tracking->devicesForActor($user);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(10, (int) $request->query('per_page', 25)));
        $unreadOnly = $request->boolean('unread_only');

        if ($devices->isEmpty()) {
            return $this->noStoreJson([
                'notifications' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => false,
                'unread_count' => 0,
            ]);
        }

        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->subDays(self::DEFAULT_HISTORY_DAYS);
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now();

        $devicesById = $devices->take(self::MAX_DEVICES)->keyBy('id');
        $readIds = $this->readEventIds((int) $user->id);
        $type = $request->query('type');
        $types = $type ? [(string) $type] : null;

        $all = [];
        foreach ($devicesById as $device) {
            foreach ($this->events->forDevice($device, $from, $to, $types, limit: 200) as $event) {
                if (! $type && $this->notifications->isWebSuppressed($user, $event->type)) {
                    continue;
                }
                $read = in_array((int) $event->id, $readIds, true);
                if ($unreadOnly && $read) {
                    continue;
                }
                $all[] = array_merge($event->toAlertArray(), [
                    'device_id' => $device->id,
                    'device_name' => $device->mapMarkerTitle(),
                    'lat' => $event->lat,
                    'lng' => $event->lng,
                    'read' => $read,
                ]);
            }
        }

        usort($all, fn ($a, $b) => strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? '')));
        $total = count($all);
        $unreadCount = count(array_filter($all, fn ($row) => empty($row['read'])));
        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);

        return $this->noStoreJson([
            'notifications' => $slice,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $total,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'integer',
        ]);

        $user = $request->user();
        $ids = collect($validated['alert_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $now = now();
        $rows = $ids->map(fn (int $id) => [
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

        return $this->noStoreJson([
            'success' => true,
            'marked' => count($rows),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.*.type' => 'required|string',
            'preferences.*.web' => 'boolean',
            'preferences.*.push' => 'boolean',
            'preferences.*.email' => 'boolean',
            'preferences.*.whatsapp' => 'boolean',
        ]);

        $this->notifications->update($request->user(), $validated['preferences']);

        return $this->noStoreJson(['success' => true]);
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

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function viewData(Request $request, string $panel, array $extra = []): array
    {
        return array_merge([
            'panel' => $panel,
            'layout' => $this->layoutForPanel($panel),
            'hubRoutes' => $this->trackingHubRoutes($panel),
            'trackingUi' => $this->trackingUiFor($request->user()),
        ], $extra);
    }
}
