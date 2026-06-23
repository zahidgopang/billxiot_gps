<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\TraccarEntityMap;
use App\Services\Traccar\TraccarIdMap;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrackingMetricsService
{
    public function __construct(
        private PositionReaderInterface $positions,
        private EventReaderInterface $events,
        private TraccarIdMap $idMap,
    ) {}

    public function calculateTotalDistanceKm(Collection $deviceIds, int $days = 30): float
    {
        if ($deviceIds->isEmpty()) {
            return 0;
        }

        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            return $this->distanceFromTraccar($deviceIds, $days);
        }

        return $this->distanceFromLegacy($deviceIds, $days);
    }

    public function positionCountSince(Carbon $from): int
    {
        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            return (int) DB::table(config('traccar.tables.positions', 'tc_positions'))
                ->where('fixtime', '>=', $from)
                ->count();
        }

        if (! Schema::hasTable('device_locations')) {
            return 0;
        }

        return (int) DB::table('device_locations')
            ->where('recorded_at', '>=', $from)
            ->count();
    }

    public function activeTrackingDays(Collection $deviceIds, int $days = 30): int
    {
        if ($deviceIds->isEmpty()) {
            return 0;
        }

        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            $traccarIds = $deviceIds
                ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
                ->filter()
                ->values()
                ->all();

            if ($traccarIds === []) {
                return 0;
            }

            $from = now()->subDays($days);

            return (int) DB::table(config('traccar.tables.positions', 'tc_positions'))
                ->whereIn('deviceid', $traccarIds)
                ->where('fixtime', '>=', $from)
                ->selectRaw('COUNT(DISTINCT DATE(fixtime)) as days')
                ->value('days');
        }

        if (! Schema::hasTable('device_locations')) {
            return 0;
        }

        return (int) DB::table('device_locations')
            ->whereIn('device_id', $deviceIds)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->selectRaw('COUNT(DISTINCT DATE(recorded_at)) as days')
            ->value('days');
    }

    /**
     * @return array{labels: array<int, string>, gpsPings: array<int, int>, activeDevices: array<int, int>}
     */
    public function positionChartData(int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $labels = [];
        $gpsCounts = [];
        $activeDeviceCounts = [];

        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            $table = config('traccar.tables.positions', 'tc_positions');
            $gpsByDay = DB::table($table)
                ->where('fixtime', '>=', $start)
                ->selectRaw('DATE(fixtime) as day, COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
            $devicesByDay = DB::table($table)
                ->where('fixtime', '>=', $start)
                ->selectRaw('DATE(fixtime) as day, COUNT(DISTINCT deviceid) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        } elseif (Schema::hasTable('device_locations')) {
            $gpsByDay = DB::table('device_locations')
                ->where('recorded_at', '>=', $start)
                ->selectRaw('DATE(recorded_at) as day, COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
            $devicesByDay = DB::table('device_locations')
                ->where('recorded_at', '>=', $start)
                ->selectRaw('DATE(recorded_at) as day, COUNT(DISTINCT device_id) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
        } else {
            $gpsByDay = collect();
            $devicesByDay = collect();
        }

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            $gpsCounts[] = (int) ($gpsByDay[$key] ?? 0);
            $activeDeviceCounts[] = (int) ($devicesByDay[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'gpsPings' => $gpsCounts,
            'activeDevices' => $activeDeviceCounts,
        ];
    }

    public function onlineDevicesAt(Carbon $at, int $windowMinutes = 5): int
    {
        $from = $at->copy()->subMinutes($windowMinutes);
        $to = $at;

        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            return (int) DB::table(config('traccar.tables.positions', 'tc_positions'))
                ->whereBetween('fixtime', [$from, $to])
                ->distinct('deviceid')
                ->count('deviceid');
        }

        if (! Schema::hasTable('device_locations')) {
            return 0;
        }

        return (int) DB::table('device_locations')
            ->whereBetween('recorded_at', [$from, $to])
            ->distinct('device_id')
            ->count('device_id');
    }

    public function lastPositionAt(): ?Carbon
    {
        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            $max = DB::table(config('traccar.tables.positions', 'tc_positions'))->max('fixtime');

            return $max ? Carbon::parse($max) : null;
        }

        if (! Schema::hasTable('device_locations')) {
            return null;
        }

        $max = DB::table('device_locations')->max('recorded_at');

        return $max ? Carbon::parse($max) : null;
    }

    private function distanceFromLegacy(Collection $deviceIds, int $days): float
    {
        if (! Schema::hasTable('device_locations')) {
            return 0.0;
        }

        $locations = DB::table('device_locations')
            ->whereIn('device_id', $deviceIds)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('device_id')
            ->orderBy('recorded_at')
            ->get(['device_id', 'lat', 'lng']);

        return $this->sumHaversineChain($locations);
    }

    private function distanceFromTraccar(Collection $deviceIds, int $days): float
    {
        $traccarIds = $deviceIds
            ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
            ->filter()
            ->values();

        if ($traccarIds->isEmpty()) {
            return 0;
        }

        $total = 0.0;

        foreach (Device::query()->whereIn('id', $deviceIds)->get() as $device) {
            $history = $this->positions->historyForDevice($device, now()->subDays($days), null, 'asc');
            $prev = null;

            foreach ($history as $loc) {
                if ($prev instanceof DeviceLocation) {
                    $total += $this->haversineKm(
                        (float) $prev->lat,
                        (float) $prev->lng,
                        (float) $loc->lat,
                        (float) $loc->lng
                    );
                }
                $prev = $loc;
            }
        }

        return $total;
    }

    private function sumHaversineChain(Collection $locations): float
    {
        $totalKm = 0;
        $prev = null;
        $prevDeviceId = null;

        foreach ($locations as $loc) {
            if ($prev && $prevDeviceId === $loc->device_id) {
                $totalKm += $this->haversineKm(
                    (float) $prev->lat,
                    (float) $prev->lng,
                    (float) $loc->lat,
                    (float) $loc->lng
                );
            }
            $prev = $loc;
            $prevDeviceId = $loc->device_id;
        }

        return $totalKm;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * asin(sqrt($a));
    }
}
