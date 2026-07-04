<?php

namespace App\Services\Routes;

use App\Models\TripLog;
use App\Support\Geo\GeoMath;
use App\Support\Geo\PolylineDecoder;
use App\Support\Geo\PolylineEncoder;
use Carbon\Carbon;

/**
 * Records the actual GPS-driven path during an active trip session.
 */
class ActualRouteRecorder
{
    private const MIN_SEGMENT_METERS = 25;

    /**
     * @return array<string, mixed>
     */
    public function defaultStatistics(): array
    {
        return [
            'max_speed_kmh' => 0.0,
            'avg_speed_kmh' => 0.0,
            'moving_time_seconds' => 0,
            'idle_time_seconds' => 0,
            'stopped_time_seconds' => 0,
            'navigation_recalculations' => 0,
            'gps_accuracy_m' => null,
            'point_count' => 0,
            'speed_samples' => 0,
            'speed_sum_kmh' => 0.0,
            'last_update_at' => null,
        ];
    }

    public function record(
        TripLog $trip,
        float $lat,
        float $lng,
        ?float $speedKmh = null,
        ?float $accuracyM = null,
        bool $positionIsLive = true,
    ): TripLog {
        if (! $trip->isSessionActive() || ! $positionIsLive) {
            return $trip;
        }

        $stats = array_merge($this->defaultStatistics(), is_array($trip->trip_statistics) ? $trip->trip_statistics : []);
        $now = now();

        if (! empty($stats['last_update_at'])) {
            $seconds = max(0, (int) Carbon::parse($stats['last_update_at'])->diffInSeconds($now));
            $speed = max(0, (float) ($speedKmh ?? 0));
            if ($speed < 1) {
                $stats['stopped_time_seconds'] = (int) $stats['stopped_time_seconds'] + $seconds;
            } elseif ($speed < 5) {
                $stats['idle_time_seconds'] = (int) $stats['idle_time_seconds'] + $seconds;
            } else {
                $stats['moving_time_seconds'] = (int) $stats['moving_time_seconds'] + $seconds;
            }
        }

        if ($speedKmh !== null && $speedKmh >= 0) {
            $stats['max_speed_kmh'] = max((float) $stats['max_speed_kmh'], $speedKmh);
            $stats['speed_samples'] = (int) $stats['speed_samples'] + 1;
            $stats['speed_sum_kmh'] = (float) $stats['speed_sum_kmh'] + $speedKmh;
            $stats['avg_speed_kmh'] = $stats['speed_samples'] > 0
                ? round($stats['speed_sum_kmh'] / $stats['speed_samples'], 1)
                : 0.0;
        }

        if ($accuracyM !== null && $accuracyM > 0) {
            $stats['gps_accuracy_m'] = round($accuracyM, 1);
        }

        $vertices = $trip->actual_route_polyline
            ? PolylineDecoder::decode($trip->actual_route_polyline)
            : [];

        $shouldAppend = true;
        if ($vertices !== []) {
            $last = $vertices[count($vertices) - 1];
            $segmentMeters = GeoMath::haversineKm(
                (float) $last['lat'],
                (float) $last['lng'],
                $lat,
                $lng,
            ) * 1000;
            $shouldAppend = $segmentMeters >= self::MIN_SEGMENT_METERS;
            if ($shouldAppend) {
                $trip->actual_distance_km = round((float) $trip->actual_distance_km + ($segmentMeters / 1000), 3);
            }
        } elseif ($trip->start_lat !== null && $trip->start_lng !== null) {
            $trip->actual_distance_km = round(GeoMath::haversineKm(
                (float) $trip->start_lat,
                (float) $trip->start_lng,
                $lat,
                $lng,
            ), 3);
            $vertices[] = ['lat' => (float) $trip->start_lat, 'lng' => (float) $trip->start_lng];
        }

        if ($shouldAppend) {
            $vertices[] = ['lat' => $lat, 'lng' => $lng];
            $trip->actual_route_polyline = PolylineEncoder::encode($vertices);
            $stats['point_count'] = count($vertices);
        }

        $expected = (float) ($trip->expected_distance_km ?? 0);
        $trip->extra_distance_km = $expected > 0
            ? round(max(0, (float) $trip->actual_distance_km - $expected), 3)
            : 0;

        $stats['last_update_at'] = $now->toIso8601String();
        $trip->trip_statistics = $stats;
        $trip->last_lat = $lat;
        $trip->last_lng = $lng;

        return $trip;
    }

    /**
     * @return list<array{lat: float, lng: float}>
     */
    public function vertices(TripLog $trip): array
    {
        if (! $trip->actual_route_polyline) {
            return [];
        }

        return PolylineDecoder::decode($trip->actual_route_polyline);
    }
}
