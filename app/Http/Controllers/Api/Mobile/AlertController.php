<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Contracts\Tracking\EventReaderInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Models\Device;
use App\Models\VehicleEvent;
use App\Models\VehicleEventRead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlertController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private EventReaderInterface $events,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $devices = $user->trackableDevicesQuery()->get();
        $deviceIds = $devices->pluck('id');

        if ($deviceIds->isEmpty()) {
            return $this->mobileSuccess([]);
        }

        $devicesById = $devices->keyBy('id');
        $readIds = $this->readEventIds($user->id);
        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(30);
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : null;

        $events = $this->fetchFleetEvents($deviceIds, $devicesById, $from, $to, $limit, $request);

        return $this->mobileSuccess(
            $events->map(fn (VehicleEvent $e) => $this->formatAlert($e, $readIds, $devicesById))->values()
        );
    }

    public function unread(Request $request)
    {
        $user = $request->user();
        $devices = $user->trackableDevicesQuery()->get();
        $deviceIds = $devices->pluck('id');

        if ($deviceIds->isEmpty()) {
            return $this->mobileSuccess(['count' => 0, 'alerts' => []]);
        }

        $devicesById = $devices->keyBy('id');
        $readIds = $this->readEventIds($user->id);

        $events = $this->fetchFleetEvents(
            $deviceIds,
            $devicesById,
            now()->subDays(30),
            null,
            50,
            $request
        )->filter(fn (VehicleEvent $e) => ! in_array($e->id, $readIds, true));

        return $this->mobileSuccess([
            'count' => $events->count(),
            'alerts' => $events
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
        $deviceIds = $user->trackableDevicesQuery()->pluck('id');

        $validIds = collect($validated['alert_ids'])
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
     * Same sources as web alerts (Traccar tc_events when configured, else vehicle_events).
     *
     * @param  \Illuminate\Support\Collection<int, Device>  $devicesById
     * @return \Illuminate\Support\Collection<int, VehicleEvent>
     */
    private function fetchFleetEvents(
        $deviceIds,
        $devicesById,
        Carbon $from,
        ?Carbon $to,
        int $limit,
        Request $request,
    ) {
        if ($request->filled('device_id')) {
            $device = $devicesById->get((int) $request->device_id);
            if ($device) {
                return $this->events->forDevice($device, $from, $to, limit: $limit);
            }

            return collect();
        }

        $events = $this->events->recentForDevices($deviceIds, $limit);

        if ($events->isEmpty()) {
            $merged = collect();
            foreach ($devicesById as $device) {
                $merged = $merged->merge(
                    $this->events->forDevice($device, $from, $to, limit: (int) ceil($limit / max(1, $devicesById->count())))
                );
            }

            $events = $merged
                ->sortByDesc(fn (VehicleEvent $e) => $e->occurred_at)
                ->take($limit)
                ->values();
        }

        if ($request->filled('device_id')) {
            $deviceId = (int) $request->device_id;
            $events = $events->filter(fn (VehicleEvent $e) => (int) $e->device_id === $deviceId)->values();
        }

        if ($request->filled('from')) {
            $fromFilter = Carbon::parse($request->input('from'))->startOfDay();
            $events = $events->filter(
                fn (VehicleEvent $e) => $e->occurred_at && $e->occurred_at >= $fromFilter
            )->values();
        }

        if ($request->filled('to')) {
            $toFilter = Carbon::parse($request->input('to'))->endOfDay();
            $events = $events->filter(
                fn (VehicleEvent $e) => $e->occurred_at && $e->occurred_at <= $toFilter
            )->values();
        }

        return $events;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Device>  $devicesById
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
            ->all();
    }
}
