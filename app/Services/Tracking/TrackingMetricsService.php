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
     * Distance (km) for multiple day windows in one pass (shared latest odometer).
     *
     * @param  list<int>  $daysList  rolling windows via now()->subDays($n)
     * @return array<int, float>  keyed by day window
     */
    public function calculateTotalDistanceKmByWindows(Collection $deviceIds, array $daysList): array
    {
        $periods = [];
        foreach (array_values(array_unique(array_map('intval', $daysList))) as $days) {
            $periods[$days] = now()->subDays($days);
        }

        return $this->calculateTotalDistanceKmForPeriods($deviceIds, $periods);
    }

    /**
     * Distance (km) since each absolute start time (app timezone → UTC for Traccar).
     *
     * @param  array<string|int, Carbon>  $periods  e.g. ['today' => now()->startOfDay(), 30 => now()->subDays(30)]
     * @return array<string|int, float>
     */
    public function calculateTotalDistanceKmForPeriods(Collection $deviceIds, array $periods): array
    {
        $out = [];
        foreach ($periods as $key => $from) {
            $out[$key] = 0.0;
        }

        if ($deviceIds->isEmpty() || $periods === []) {
            return $out;
        }

        if (! (TraccarMode::readsTraccar() && TraccarSchema::isReady())) {
            foreach ($periods as $key => $from) {
                $out[$key] = $this->distanceFromLegacySince($deviceIds, $from instanceof Carbon ? $from : now()->subDays((int) $from));
            }

            return $out;
        }

        $traccarIds = $deviceIds
            ->map(fn ($id) => $this->idMap->get(TraccarEntityMap::TYPE_DEVICE, (int) $id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($traccarIds === []) {
            return $out;
        }

        $fromUtcByKey = [];
        foreach ($periods as $key => $from) {
            $fromUtcByKey[$key] = $this->utc($from instanceof Carbon ? $from : now()->subDays((int) $from));
        }

        $widestFrom = collect($fromUtcByKey)->sort()->first();
        $table = config('traccar.tables.positions', 'tc_positions');
        $totals = array_fill_keys(array_keys($periods), 0.0);

        foreach ($traccarIds as $traccarDeviceId) {
            $lastAttrs = DB::table($table)
                ->where('deviceid', $traccarDeviceId)
                ->where('fixtime', '>=', $widestFrom)
                ->orderByDesc('fixtime')
                ->orderByDesc('id')
                ->value('attributes');
            $last = $this->odometerMetersFromAttributes($lastAttrs);
            if ($last === null) {
                continue;
            }

            foreach ($fromUtcByKey as $key => $fromUtc) {
                $firstAttrs = DB::table($table)
                    ->where('deviceid', $traccarDeviceId)
                    ->where('fixtime', '>=', $fromUtc)
                    ->orderBy('fixtime')
                    ->orderBy('id')
                    ->value('attributes');
                $first = $this->odometerMetersFromAttributes($firstAttrs);
                if ($first === null) {
                    continue;
                }

                $delta = $last - $first;
                if ($delta < 0 || $delta > 20_000_000) {
                    continue;
                }
                $totals[$key] += $delta;
            }
        }

        foreach ($totals as $key => $meters) {
            $out[$key] = $meters / 1000;
        }

        return $out;
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
                // Full-fleet admin chart: DATE(fixtime) stays index-friendly.
                // CONVERT_TZ() over all tc_positions regularly times out on production.
                $gpsByDay = $gpsQuery
                    ->selectRaw('DATE(fixtime) as day, COUNT(*) as total')
                    ->groupBy('day')
                    ->pluck('total', 'day');
                $devicesByDay = $devQuery
                    ->selectRaw('DATE(fixtime) as day, COUNT(DISTINCT deviceid) as total')
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
            $devicesTable = config('traccar.tables.devices', 'tc_devices');
            $positionsTable = config('traccar.tables.positions', 'tc_positions');

            // Use each device's current positionid (O(devices)) instead of MAX(fixtime)
            // over all tc_positions, which times out on production GPS tables.
            $max = DB::table($devicesTable.' as d')
                ->join($positionsTable.' as p', 'd.positionid', '=', 'p.id')
                ->max('p.fixtime');

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
        return $this->distanceFromLegacySince($deviceIds, now()->subDays($days));
    }

    private function distanceFromLegacySince(Collection $deviceIds, Carbon $from): float
    {
        if (! Schema::hasTable('device_locations')) {
            return 0.0;
        }

        $locations = DB::table('device_locations')
            ->whereIn('device_id', $deviceIds)
            ->where('recorded_at', '>=', $from)
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
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($traccarIds === []) {
            return 0;
        }

        $fromUtc = $this->utc(now()->subDays($days));
        $table = config('traccar.tables.positions', 'tc_positions');
        $totalMeters = 0.0;

        // Per-device indexed LIMIT 1 lookups (odometer first→last). Full haversine over
        // millions of rows never finished and left dashboard Total Distance as "—".
        foreach ($traccarIds as $traccarDeviceId) {
            $delta = $this->odometerDeltaMeters($table, $traccarDeviceId, $fromUtc);
            if ($delta !== null) {
                $totalMeters += $delta;
            }
        }

        return $totalMeters / 1000;
    }

    /**
     * Device-reported odometer delta in meters for [fromUtc, now], or null when unavailable.
     */
    private function odometerDeltaMeters(string $table, int $traccarDeviceId, Carbon $fromUtc): ?float
    {
        $firstAttrs = DB::table($table)
            ->where('deviceid', $traccarDeviceId)
            ->where('fixtime', '>=', $fromUtc)
            ->orderBy('fixtime')
            ->orderBy('id')
            ->value('attributes');

        $lastAttrs = DB::table($table)
            ->where('deviceid', $traccarDeviceId)
            ->where('fixtime', '>=', $fromUtc)
            ->orderByDesc('fixtime')
            ->orderByDesc('id')
            ->value('attributes');

        $first = $this->odometerMetersFromAttributes($firstAttrs);
        $last = $this->odometerMetersFromAttributes($lastAttrs);

        if ($first === null || $last === null) {
            return null;
        }

        $delta = $last - $first;

        // Ignore resets / wrap / garbage jumps (cap ~20,000 km per device per window).
        if ($delta < 0 || $delta > 20_000_000) {
            return null;
        }

        return $delta;
    }

    private function odometerMetersFromAttributes(mixed $attributes): ?float
    {
        if ($attributes === null || $attributes === '') {
            return null;
        }

        $attrs = is_array($attributes)
            ? $attributes
            : json_decode((string) $attributes, true);

        if (! is_array($attrs)) {
            return null;
        }

        $raw = $attrs['odometer'] ?? $attrs['totalDistance'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return (float) $raw;
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
