<?php

namespace App\Services\Tracking\Reports;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\HistoryAnalyticsService;
use App\Support\DateTime\AppDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public const MAX_DEVICES = 50;

    public function __construct(
        private DeviceHistoryFetcher $historyFetcher,
        private HistoryAnalyticsService $historyAnalytics,
        private EventReaderInterface $events,
        private GlobalTrackingService $tracking,
    ) {}

    /**
     * @param  list<int>  $deviceIds
     * @return array<string, mixed>
     */
    public function generate(User $actor, string $type, array $deviceIds, Carbon $from, ?Carbon $to): array
    {
        $ids = array_slice(
            $this->tracking->filterAllowedIds($actor, $deviceIds),
            0,
            self::MAX_DEVICES
        );

        if ($ids === []) {
            return ['type' => $type, 'devices' => [], 'from' => $from->toIso8601String(), 'to' => $to?->toIso8601String()];
        }

        $devices = Device::query()->whereIn('id', $ids)->get()->keyBy('id');
        $results = [];

        foreach ($ids as $id) {
            $device = $devices->get($id);
            if (! $device) {
                continue;
            }

            $results[] = match ($type) {
                'route' => $this->routeReport($device, $from, $to),
                'positions' => $this->positionsReport($device, $from, $to),
                'trips' => $this->tripsReport($device, $from, $to),
                'stops' => $this->stopsReport($device, $from, $to),
                'summary' => $this->summaryReport($device, $from, $to),
                'events' => $this->eventsReport($device, $from, $to),
                default => $this->summaryReport($device, $from, $to),
            };
        }

        return [
            'type' => $type,
            'from' => $from->toIso8601String(),
            'to' => $to?->toIso8601String(),
            'devices' => $results,
            'totals' => $this->aggregateTotals($type, $results),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $points = $this->loadPoints($device, $from, $to);
        $stats = $this->historyAnalytics->analyze($collection);
        $trips = $this->tripsFromTimeline($collection, $stats['timeline'] ?? []);

        return array_merge($this->deviceMeta($device), [
            'point_count' => count($points),
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'stopped_time_seconds' => $stats['stopped_time_seconds'] ?? 0,
            'idle_time_seconds' => $stats['idle_time_seconds'] ?? 0,
            'parking_time_seconds' => $stats['parking_time_seconds'] ?? 0,
            'offline_time_seconds' => $stats['offline_time_seconds'] ?? 0,
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            'total_duration_seconds' => $stats['total_duration_seconds'] ?? 0,
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'trip_count' => count($trips),
            'points' => $points,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function positionsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $statuses = $this->historyAnalytics->pointStatuses($collection);
        $positions = [];

        foreach ($collection->values() as $index => $loc) {
            $status = $statuses[$index] ?? [];
            $positions[] = [
                'time' => AppDateTime::toApi($loc->recorded_at),
                'time_display' => app_datetime_format($loc->recorded_at),
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'speed' => round((float) ($loc->speed ?? 0), 1),
                'heading' => isset($loc->heading) ? (float) $loc->heading : null,
                'ignition' => (bool) $loc->ignition,
                'status' => (string) ($status['motion_status'] ?? $status['status_label'] ?? ''),
                'status_key' => (string) ($status['motion_status_key'] ?? $status['status_key'] ?? ''),
            ];
        }

        return array_merge($this->deviceMeta($device), [
            'positions' => $positions,
            'position_count' => count($positions),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tripsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->historyAnalytics->analyze($collection);
        $trips = $this->tripsFromTimeline($collection, $stats['timeline'] ?? []);

        return array_merge($this->deviceMeta($device), [
            'trips' => $trips,
            'trip_count' => count($trips),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stopsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->historyAnalytics->analyze($collection);

        return array_merge($this->deviceMeta($device), [
            'stops' => $stats['stops'] ?? [],
            'stop_count' => $stats['stop_count'] ?? 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->historyAnalytics->analyze($collection);
        $trips = $this->tripsFromTimeline($collection, $stats['timeline'] ?? []);

        return array_merge($this->deviceMeta($device), [
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'stopped_time_seconds' => $stats['stopped_time_seconds'] ?? 0,
            'idle_time_seconds' => $stats['idle_time_seconds'] ?? 0,
            'parking_time_seconds' => $stats['parking_time_seconds'] ?? 0,
            'offline_time_seconds' => $stats['offline_time_seconds'] ?? 0,
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            'overspeed_events' => $stats['overspeed_events'] ?? 0,
            'stop_count' => $stats['stop_count'] ?? 0,
            'trip_count' => count($trips),
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'total_duration_seconds' => $stats['total_duration_seconds'] ?? 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $events = $this->events
            ->forDevice($device, $from, $to, limit: 1000)
            ->map(fn (VehicleEvent $event) => array_merge($event->toAlertArray(), [
                'lat' => $event->lat !== null ? (float) $event->lat : null,
                'lng' => $event->lng !== null ? (float) $event->lng : null,
            ]))
            ->values()
            ->all();

        return array_merge($this->deviceMeta($device), [
            'events' => $events,
            'event_count' => count($events),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceMeta(Device $device): array
    {
        return [
            'device_id' => $device->id,
            'device_name' => $device->mapMarkerTitle(),
            'plate' => $device->vehiclePlateNumber() ?? '',
            'driver' => $device->driverDisplayName() ?? '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $devices
     * @return array<string, mixed>
     */
    private function aggregateTotals(string $type, array $devices): array
    {
        if ($devices === []) {
            return [];
        }

        if ($type === 'summary' || $type === 'route') {
            return [
                'device_count' => count($devices),
                'total_distance_km' => round(array_sum(array_map(
                    fn (array $d) => (float) ($d['total_distance_km'] ?? 0),
                    $devices
                )), 2),
                'moving_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['moving_time_seconds'] ?? 0),
                    $devices
                )),
                'stopped_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['stopped_time_seconds'] ?? 0),
                    $devices
                )),
                'idle_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['idle_time_seconds'] ?? 0),
                    $devices
                )),
                'parking_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['parking_time_seconds'] ?? 0),
                    $devices
                )),
                'offline_time_seconds' => array_sum(array_map(
                    fn (array $d) => (int) ($d['offline_time_seconds'] ?? 0),
                    $devices
                )),
                'max_speed_kmh' => max(array_map(
                    fn (array $d) => (float) ($d['max_speed_kmh'] ?? 0),
                    $devices
                )),
                'trip_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['trip_count'] ?? 0),
                    $devices
                )),
                'stop_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['stop_count'] ?? 0),
                    $devices
                )),
                'overspeed_events' => array_sum(array_map(
                    fn (array $d) => (int) ($d['overspeed_events'] ?? 0),
                    $devices
                )),
                'point_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['point_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'trips') {
            return [
                'device_count' => count($devices),
                'trip_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['trip_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'stops') {
            return [
                'device_count' => count($devices),
                'stop_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['stop_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'events') {
            return [
                'device_count' => count($devices),
                'event_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['event_count'] ?? 0),
                    $devices
                )),
            ];
        }

        if ($type === 'positions') {
            return [
                'device_count' => count($devices),
                'position_count' => array_sum(array_map(
                    fn (array $d) => (int) ($d['position_count'] ?? 0),
                    $devices
                )),
            ];
        }

        return ['device_count' => count($devices)];
    }

    /**
     * @return Collection<int, DeviceLocation>
     */
    private function loadLocationCollection(Device $device, Carbon $from, ?Carbon $to): Collection
    {
        return $this->historyFetcher->fetch($device, $from, $to, true)['locations'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPoints(Device $device, Carbon $from, ?Carbon $to): array
    {
        return $this->loadLocationCollection($device, $from, $to)
            ->map(fn (DeviceLocation $loc) => [
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'speed' => (float) ($loc->speed ?? 0),
                'heading' => (float) ($loc->heading ?? 0),
                'ignition' => (bool) $loc->ignition,
                'recorded_at' => AppDateTime::toApi($loc->recorded_at),
            ])
            ->values()
            ->all();
    }

    /**
     * Build trips from ignition-aware timeline segments (Traccar-style).
     *
     * @param  list<array<string, mixed>>  $timeline
     * @return list<array<string, mixed>>
     */
    private function tripsFromTimeline(Collection $points, array $timeline): array
    {
        if ($timeline === []) {
            return [];
        }

        $movingKeys = ['running', 'moving'];
        $breakKeys = ['parked', 'stopped', 'idle', 'offline'];
        $stopMin = HistoryAnalyticsService::STOP_MIN_SECONDS;
        $trips = [];
        $bucket = [];
        $breakSec = 0;

        $flush = function () use (&$trips, &$bucket, &$breakSec, $points): void {
            if ($bucket === []) {
                $breakSec = 0;

                return;
            }

            $trip = $this->buildTripFromSegments($points, $bucket);
            if ($trip !== null) {
                $trips[] = $trip;
            }

            $bucket = [];
            $breakSec = 0;
        };

        foreach ($timeline as $segment) {
            if (($segment['is_transition'] ?? false) === true) {
                continue;
            }

            $key = (string) ($segment['status_key'] ?? '');
            $dur = (int) ($segment['duration_seconds'] ?? 0);

            if (in_array($key, $movingKeys, true)) {
                if ($breakSec >= $stopMin) {
                    $flush();
                }
                $breakSec = 0;
                $bucket[] = $segment;
            } elseif (in_array($key, $breakKeys, true)) {
                $breakSec += $dur;
                if ($breakSec >= $stopMin && $bucket !== []) {
                    $flush();
                }
            }
        }

        $flush();

        return $trips;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>|null
     */
    private function buildTripFromSegments(Collection $points, array $segments): ?array
    {
        if ($segments === []) {
            return null;
        }

        $first = $segments[0];
        $last = $segments[count($segments) - 1];
        $startAt = Carbon::parse((string) ($first['start'] ?? ''));
        $endAt = Carbon::parse((string) ($last['end'] ?? ''));

        $slice = $points->filter(function ($point) use ($startAt, $endAt) {
            $at = $point->recorded_at ?? null;

            return $at instanceof Carbon && $at->betweenIncluded($startAt, $endAt);
        })->values();

        if ($slice->count() >= 2) {
            $stats = $this->historyAnalytics->analyze($slice);
        } else {
            $movingSec = array_sum(array_map(
                fn (array $segment) => (int) ($segment['duration_seconds'] ?? 0),
                $segments
            ));
            $maxSpeed = max(array_map(
                fn (array $segment) => (float) ($segment['max_speed_kmh'] ?? 0),
                $segments
            ));
            $stats = [
                'total_distance_km' => 0,
                'total_duration_seconds' => max(0, (int) $startAt->diffInSeconds($endAt)),
                'moving_time_seconds' => $movingSec,
                'max_speed_kmh' => $maxSpeed,
                'average_speed_kmh' => 0,
            ];
        }

        return [
            'start_time' => (string) ($first['start_display'] ?? $first['start'] ?? ''),
            'end_time' => (string) ($last['end_display'] ?? $last['end'] ?? ''),
            'start_lat' => $first['start_lat'] ?? null,
            'start_lng' => $first['start_lng'] ?? null,
            'end_lat' => $last['end_lat'] ?? null,
            'end_lng' => $last['end_lng'] ?? null,
            'distance_km' => $stats['total_distance_km'] ?? 0,
            'duration_seconds' => $stats['total_duration_seconds'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
        ];
    }
}
