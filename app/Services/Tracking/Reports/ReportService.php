<?php

namespace App\Services\Tracking\Reports;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\VehicleEvent;
use App\Services\Mobile\MobileRouteAnalyticsService;
use App\Services\Tracking\DeviceHistoryFetcher;
use App\Services\Tracking\GlobalTrackingService;
use App\Support\DateTime\AppDateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public const MAX_DEVICES = 50;

    public function __construct(
        private DeviceHistoryFetcher $historyFetcher,
        private MobileRouteAnalyticsService $analytics,
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $points = $this->loadPoints($device, $from, $to);

        return [
            'device_id' => $device->id,
            'device_name' => $device->mapMarkerTitle(),
            'point_count' => count($points),
            'points' => $points,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tripsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $trips = $this->segmentTrips($collection);

        return [
            'device_id' => $device->id,
            'device_name' => $device->mapMarkerTitle(),
            'trips' => $trips,
            'trip_count' => count($trips),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stopsReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->analytics->analyze($collection);

        return [
            'device_id' => $device->id,
            'device_name' => $device->mapMarkerTitle(),
            'stops' => $stats['stops'] ?? [],
            'stop_count' => $stats['stop_count'] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryReport(Device $device, Carbon $from, ?Carbon $to): array
    {
        $collection = $this->loadLocationCollection($device, $from, $to);
        $stats = $this->analytics->analyze($collection);

        return [
            'device_id' => $device->id,
            'device_name' => $device->mapMarkerTitle(),
            'total_distance_km' => $stats['total_distance_km'] ?? 0,
            'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
            'stopped_time_seconds' => $stats['stopped_time_seconds'] ?? 0,
            'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
            'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            'overspeed_events' => $stats['overspeed_events'] ?? 0,
            'stop_count' => $stats['stop_count'] ?? 0,
            'start_time' => $stats['start_time'] ?? null,
            'end_time' => $stats['end_time'] ?? null,
            'total_duration_seconds' => $stats['total_duration_seconds'] ?? 0,
        ];
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

        return [
            'device_id' => $device->id,
            'device_name' => $device->mapMarkerTitle(),
            'events' => $events,
            'event_count' => count($events),
        ];
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
     * @param  Collection<int, DeviceLocation>  $points
     * @return list<array<string, mixed>>
     */
    private function segmentTrips(Collection $points): array
    {
        if ($points->isEmpty()) {
            return [];
        }

        $data = $points->values()->all();
        $stopMinSec = MobileRouteAnalyticsService::STOP_MIN_SECONDS;
        $trips = [];
        $segmentStart = 0;
        $stopRun = [];

        $flushTrip = function (int $endIdx) use (&$trips, &$segmentStart, $data): void {
            if ($endIdx <= $segmentStart) {
                return;
            }

            $slice = array_slice($data, $segmentStart, $endIdx - $segmentStart + 1);
            if (count($slice) < 2) {
                return;
            }

            $stats = $this->analytics->analyze(collect($slice));
            $first = $slice[0];
            $last = $slice[count($slice) - 1];

            $trips[] = [
                'start_time' => $stats['start_time'] ?? AppDateTime::toApi($first->recorded_at),
                'end_time' => $stats['end_time'] ?? AppDateTime::toApi($last->recorded_at),
                'start_lat' => (float) $first->lat,
                'start_lng' => (float) $first->lng,
                'end_lat' => (float) $last->lat,
                'end_lng' => (float) $last->lng,
                'distance_km' => $stats['total_distance_km'] ?? 0,
                'duration_seconds' => $stats['total_duration_seconds'] ?? 0,
                'moving_time_seconds' => $stats['moving_time_seconds'] ?? 0,
                'max_speed_kmh' => $stats['max_speed_kmh'] ?? 0,
                'average_speed_kmh' => $stats['average_speed_kmh'] ?? 0,
            ];
        };

        for ($i = 0; $i < count($data); $i++) {
            $spd = (float) ($data[$i]->speed ?? 0);

            if ($spd < MobileRouteAnalyticsService::STOP_SPEED_KMH) {
                $stopRun[] = $i;
            } else {
                if (count($stopRun) >= 2) {
                    $t0 = $data[$stopRun[0]]->recorded_at;
                    $t1 = $data[$stopRun[count($stopRun) - 1]]->recorded_at;
                    if ($t0 && $t1 && $t1->diffInSeconds($t0) >= $stopMinSec) {
                        $flushTrip($stopRun[0] - 1);
                        $segmentStart = $stopRun[count($stopRun) - 1] + 1;
                    }
                }
                $stopRun = [];
            }
        }

        $flushTrip(count($data) - 1);

        return $trips;
    }
}
