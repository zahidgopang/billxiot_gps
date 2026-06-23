<?php

namespace App\Http\Controllers;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Models\VehicleEvent;
use App\Services\Traccar\TraccarIdMap;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VehicleAlertController extends Controller
{
    public function __construct(
        private EventReaderInterface $events,
        private TraccarIdMap $idMap,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $devices = $user->trackableDevicesQuery()
            ->orderBy('name')
            ->get(Device::listSelectColumns());

        $deviceIds = $devices->pluck('id');

        $selectedDevice = null;
        if ($request->filled('device_id')) {
            $selectedDevice = $devices->firstWhere('id', (int) $request->device_id);
        }

        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));

        if (TraccarMode::isSingleSource() && TraccarSchema::hasEvents()) {
            $events = $this->paginateTraccarEvents($deviceIds, $selectedDevice, $request, $perPage, $page);
            $eventTypes = $this->traccarEventTypes($deviceIds);
        } else {
            $query = VehicleEvent::query()
                ->with([Device::eagerListRelation(), 'geofence:id,name'])
                ->whereIn('device_id', $deviceIds)
                ->orderByDesc('occurred_at');

            if ($selectedDevice) {
                $query->where('device_id', $selectedDevice->id);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('from')) {
                $query->whereDate('occurred_at', '>=', $request->from);
            }

            if ($request->filled('to')) {
                $query->whereDate('occurred_at', '<=', $request->to);
            }

            $events = $query->paginate($perPage)->withQueryString();

            $eventTypes = VehicleEvent::query()
                ->whereIn('device_id', $deviceIds)
                ->distinct()
                ->orderBy('type')
                ->pluck('type');
        }

        return view('user.alerts.index', compact(
            'events',
            'devices',
            'selectedDevice',
            'eventTypes'
        ));
    }

    private function paginateTraccarEvents($deviceIds, ?Device $selectedDevice, Request $request, int $perPage, int $page): LengthAwarePaginator
    {
        $traccarDeviceIds = $deviceIds
            ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
            ->filter()
            ->values()
            ->all();

        $query = DB::table(config('traccar.tables.events', 'tc_events'))
            ->when($traccarDeviceIds !== [], fn ($q) => $q->whereIn('deviceid', $traccarDeviceIds))
            ->orderByDesc('eventtime');

        if ($selectedDevice) {
            $tid = $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, $selectedDevice->id);
            if ($tid) {
                $query->where('deviceid', $tid);
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('eventtime', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('eventtime', '<=', $request->to);
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();

        $items = $rows->map(function ($row) use ($deviceIds) {
            $laravelDeviceId = 0;
            foreach ($deviceIds as $lid) {
                if ($this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $lid) === (int) $row->deviceid) {
                    $laravelDeviceId = (int) $lid;
                    break;
                }
            }

            return app(\App\Repositories\Tracking\TraccarEventMapper::class)
                ->toVehicleEvent($row, $laravelDeviceId);
        });

        if ($request->filled('type')) {
            $items = $items->filter(fn (VehicleEvent $e) => $e->type === $request->type)->values();
        }

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    private function traccarEventTypes($deviceIds)
    {
        $traccarDeviceIds = $deviceIds
            ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
            ->filter()
            ->values()
            ->all();

        if ($traccarDeviceIds === []) {
            return collect();
        }

        return DB::table(config('traccar.tables.events', 'tc_events'))
            ->whereIn('deviceid', $traccarDeviceIds)
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->map(fn ($t) => app(\App\Repositories\Tracking\TraccarEventMapper::class)->reverseMapType((string) $t));
    }
}
