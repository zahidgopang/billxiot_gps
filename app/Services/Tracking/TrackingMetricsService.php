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

    /**
     * tc_positions.fixtime is stored in UTC; convert app-tz instants before
     * comparing so dashboard metrics are not off by the timezone offset.
     */
    private function utc(Carbon $dt): Carbon
    {
        return $dt->copy()->utc();
    }

    /** Current app-timezone offset (e.g. "+03:00") for SQL CONVERT_TZ. */
    private function appOffset(): string
    {
        return now()->format('P');
    }

    public function positionCountSince(Carbon $from): int
    {
        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            return (int) DB::table(config('traccar.tables.positions', 'tc_positions'))
                ->where('fixtime', '>=', $this->utc($from))
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
                ->where('fixtime', '>=', $this->utc($from))
                ->selectRaw('COUNT(DISTINCT DATE(CONVERT_TZ(fixtime, ?, ?))) as days', ['+00:00', $this->appOffset()])
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
        return $this->positionChartDataForDevices(collect(), $days);
    }

    /**
     * @return array{labels: array<int, string>, gpsPings: array<int, int>, activeDevices: array<int, int>}
     */
    public function positionChartDataForDevices(Collection $deviceIds, int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $labels = [];
        $gpsCounts = [];
        $activeDeviceCounts = [];

        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            $table = config('traccar.tables.positions', 'tc_positions');
            $off = $this->appOffset();
            $startUtc = $this->utc($start);
            $gpsQuery = DB::table($table)->where('fixtime', '>=', $startUtc);
            $devQuery = DB::table($table)->where('fixtime', '>=', $startUtc);
            $traccarIds = $deviceIds->isEmpty()
                ? null
                : $deviceIds
                    ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
                    ->filter()
                    ->values()
                    ->all();

            if ($traccarIds !== null) {
                if ($traccarIds === []) {
                    $gpsByDay = collect();
                    $devicesByDay = collect();
                } else {
                    $gpsByDay = $gpsQuery->whereIn('deviceid', $traccarIds)
                        ->selectRaw('DATE(CONVERT_TZ(fixtime, ?, ?)) as day, COUNT(*) as total', ['+00:00', $off])
                        ->groupBy('day')
                        ->pluck('total', 'day');
                    $devicesByDay = $devQuery->whereIn('deviceid', $traccarIds)
                        ->selectRaw('DATE(CONVERT_TZ(fixtime, ?, ?)) as day, COUNT(DISTINCT deviceid) as total', ['+00:00', $off])
                        ->groupBy('day')
                        ->pluck('total', 'day');
                }
            } else {
                $gpsByDay = $gpsQuery
                    ->selectRaw('DATE(CONVERT_TZ(fixtime, ?, ?)) as day, COUNT(*) as total', ['+00:00', $off])
                    ->groupBy('day')
                    ->pluck('total', 'day');
                $devicesByDay = $devQuery
                    ->selectRaw('DATE(CONVERT_TZ(fixtime, ?, ?)) as day, COUNT(DISTINCT deviceid) as total', ['+00:00', $off])
                    ->groupBy('day')
                    ->pluck('total', 'day');
            }
        } elseif (Schema::hasTable('device_locations')) {
            $gpsQuery = DB::table('device_locations')->where('recorded_at', '>=', $start);
            $devQuery = DB::table('device_locations')->where('recorded_at', '>=', $start);
            if ($deviceIds->isNotEmpty()) {
                $gpsQuery->whereIn('device_id', $deviceIds);
                $devQuery->whereIn('device_id', $deviceIds);
            }

            $gpsByDay = $gpsQuery
                ->selectRaw('DATE(recorded_at) as day, COUNT(*) as total')
                ->groupBy('day')
                ->pluck('total', 'day');
            $devicesByDay = $devQuery
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
                ->whereBetween('fixtime', [$this->utc($from), $this->utc($to)])
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

    public function onlineDevicesAtForDevices(Collection $deviceIds, Carbon $at, int $windowMinutes = 5): int
    {
        if ($deviceIds->isEmpty()) {
            return 0;
        }

        $from = $at->copy()->subMinutes($windowMinutes);
        $to = $at;

        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            $traccarIds = $deviceIds
                ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
                ->filter()
                ->values()
                ->all();

            if ($traccarIds === []) {
                return 0;
            }

            return (int) DB::table(config('traccar.tables.positions', 'tc_positions'))
                ->whereBetween('fixtime', [$this->utc($from), $this->utc($to)])
                ->whereIn('deviceid', $traccarIds)
                ->distinct('deviceid')
                ->count('deviceid');
        }

        if (! Schema::hasTable('device_locations')) {
            return 0;
        }

        return (int) DB::table('device_locations')
            ->whereBetween('recorded_at', [$from, $to])
            ->whereIn('device_id', $deviceIds)
            ->distinct('device_id')
            ->count('device_id');
    }

    public function lastPositionAt(): ?Carbon
    {
        if (TraccarMode::readsTraccar() && TraccarSchema::isReady()) {
            $max = DB::table(config('traccar.tables.positions', 'tc_positions'))->max('fixtime');

            // fixtime is UTC — parse as UTC then present in app timezone.
            return $max ? Carbon::parse($max, 'UTC')->setTimezone(config('app.timezone')) : null;
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

        $fromUtc = $this->utc(now()->subDays($days));
        $table = config('traccar.tables.positions', 'tc_positions');
        $total = 0.0;

        foreach ($traccarIds as $traccarDeviceId) {
            $prevLat = null;
            $prevLng = null;

            // Stream lat/lng only — loading full DeviceLocation collections for 30 days
            // per device exhausts memory on live fleets and caused HTTP 500 on /user/dashboard.
            foreach (DB::table($table)
                ->where('deviceid', $traccarDeviceId)
                ->where('fixtime', '>=', $fromUtc)
                ->orderBy('fixtime')
                ->orderBy('id')
                ->select(['latitude', 'longitude'])
                ->lazy(1000) as $row) {
                $lat = (float) ($row->latitude ?? 0);
                $lng = (float) ($row->longitude ?? 0);

                if ($prevLat !== null) {
                    $total += $this->haversineKm($prevLat, $prevLng, $lat, $lng);
                }

                $prevLat = $lat;
                $prevLng = $lng;
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
