<?php

namespace App\Services\Routes;

use App\Models\RoutePlan;
use App\Models\TripLog;
use App\Support\Geo\GeoMath;
use App\Support\Geo\PolylineDecoder;
use App\Support\Geo\PolylineEncoder;

/**
 * Live navigation route (vehicle → destination) separate from assigned and actual routes.
 */
class TripNavigationService
{
    public function __construct(
        private RouteGuidanceService $guidance,
        private SmartRouteMatcher $matcher,
    ) {}

    /**
     * @return array{vertices: list<array{lat: float, lng: float}>, distance_km: float, duration_minutes: int, encoded_polyline: string}|null
     */
    public function resolve(
        TripLog $trip,
        RoutePlan $route,
        float $lat,
        float $lng,
        bool $forceRecalculate = false,
    ): ?array {
        if (! $trip->isSessionActive()) {
            return null;
        }

        $context = is_array($trip->navigation_context) ? $trip->navigation_context : [];
        $stored = $this->decodeStored($trip->navigation_route_polyline);
        $needsRecalc = $forceRecalculate || $this->shouldRecalculate($context, $lat, $lng, $stored);

        if (! $needsRecalc && $stored !== null) {
            return [
                'vertices' => $stored,
                'distance_km' => (float) ($context['nav_distance_km'] ?? 0),
                'duration_minutes' => (int) ($context['nav_duration_minutes'] ?? 0),
                'encoded_polyline' => (string) $trip->navigation_route_polyline,
            ];
        }

        $result = $this->guidance->fetchNavigationRoute($route, $lat, $lng);
        if ($result === null) {
            return $stored !== null ? [
                'vertices' => $stored,
                'distance_km' => (float) ($context['nav_distance_km'] ?? 0),
                'duration_minutes' => (int) ($context['nav_duration_minutes'] ?? 0),
                'encoded_polyline' => (string) $trip->navigation_route_polyline,
            ] : null;
        }

        if ($forceRecalculate || $needsRecalc) {
            $context['navigation_recalculations'] = (int) ($context['navigation_recalculations'] ?? 0) + 1;
        }

        $context['nav_distance_km'] = $result['distance_km'];
        $context['nav_duration_minutes'] = $result['duration_minutes'];
        $context['last_nav_lat'] = $lat;
        $context['last_nav_lng'] = $lng;
        $context['last_nav_at'] = now()->toIso8601String();

        $trip->navigation_route_polyline = $result['encoded_polyline'];
        $trip->navigation_context = $context;
        $trip->remaining_distance_km = round((float) $result['distance_km'], 3);

        $stats = is_array($trip->trip_statistics) ? $trip->trip_statistics : [];
        $stats['navigation_recalculations'] = (int) ($context['navigation_recalculations'] ?? 0);
        $trip->trip_statistics = $stats;

        if ($result['duration_minutes'] > 0) {
            $trip->eta_at = now()->addMinutes((int) $result['duration_minutes']);
        }

        $trip->save();

        return $result;
    }

    /**
     * Assigned-origin → vehicle segment when the driver starts away from the planned start (e.g. Riyadh vs Makkah).
     *
     * @return list<array{lat: float, lng: float}>
     */
    public function joinPolyline(RoutePlan $route, float $lat, float $lng): array
    {
        $startLat = (float) $route->start_lat;
        $startLng = (float) $route->start_lng;
        $joinTolerance = (float) config('routes.trip_start_join_tolerance_km', 2);
        $startDistKm = GeoMath::haversineKm($lat, $lng, $startLat, $startLng);

        if ($startDistKm <= $joinTolerance) {
            return [];
        }

        $vehicle = ['lat' => $lat, 'lng' => $lng];
        $assigned = $this->guidance->pathVertices($route);
        $corridorKm = (float) config('routes.join_route_corridor_km', 5);

        if (count($assigned) >= 2) {
            $projection = $this->matcher->projectOntoPath($lat, $lng, $assigned);
            $crossTrackKm = (float) ($projection['cross_track_km'] ?? PHP_FLOAT_MAX);

            if ($crossTrackKm <= $corridorKm) {
                $prefix = $this->guidance->slicePathToDistance($assigned, (float) $projection['travelled_km']);
                if (count($prefix) >= 2) {
                    return $this->linkPathToVehicle($prefix, $vehicle);
                }
            }
        }

        $fromOrigin = $this->guidance->fetchPointToPointRoute($startLat, $startLng, $lat, $lng);

        return $fromOrigin ?? [
            ['lat' => $startLat, 'lng' => $startLng],
            $vehicle,
        ];
    }

    public function shouldShowJoinPolyline(
        RoutePlan $route,
        TripLog $trip,
        float $vehicleLat,
        float $vehicleLng,
        ?array $progress = null,
    ): bool {
        if (! $trip->isSessionActive()) {
            return false;
        }

        $startDistKm = GeoMath::haversineKm(
            $vehicleLat,
            $vehicleLng,
            (float) $route->start_lat,
            (float) $route->start_lng,
        );
        $joinTolerance = (float) config('routes.trip_start_join_tolerance_km', 2);

        if ($startDistKm <= $joinTolerance) {
            return false;
        }

        $context = is_array($trip->navigation_context) ? $trip->navigation_context : [];
        if (! empty($context['trip_start_joining'])) {
            return true;
        }

        $state = (string) ($progress['navigation_state'] ?? $context['last_state'] ?? '');

        return in_array($state, [
            SmartRouteMatcher::STATE_JOINING,
            SmartRouteMatcher::STATE_OFF_ROUTE,
            SmartRouteMatcher::STATE_RECALCULATED,
            SmartRouteMatcher::STATE_SLIGHT_DEVIATION,
        ], true) || $startDistKm > $joinTolerance;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $prefix
     * @param  array{lat: float, lng: float}  $vehicle
     * @return list<array{lat: float, lng: float}>
     */
    private function linkPathToVehicle(array $prefix, array $vehicle): array
    {
        $last = $prefix[count($prefix) - 1];
        $gapKm = GeoMath::haversineKm((float) $last['lat'], (float) $last['lng'], $vehicle['lat'], $vehicle['lng']);

        if ($gapKm < 0.3) {
            $prefix[] = $vehicle;

            return $prefix;
        }

        $link = $this->guidance->fetchPointToPointRoute(
            (float) $last['lat'],
            (float) $last['lng'],
            $vehicle['lat'],
            $vehicle['lng'],
        );

        if ($link !== null && count($link) >= 2) {
            return $this->mergeVertices($prefix, $link);
        }

        $prefix[] = $vehicle;

        return $prefix;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $a
     * @param  list<array{lat: float, lng: float}>  $b
     * @return list<array{lat: float, lng: float}>
     */
    private function mergeVertices(array $a, array $b): array
    {
        if ($a === []) {
            return $b;
        }
        if ($b === []) {
            return $a;
        }

        $last = $a[count($a) - 1];
        $first = $b[0];
        $same = abs((float) $last['lat'] - (float) $first['lat']) < 1e-5
            && abs((float) $last['lng'] - (float) $first['lng']) < 1e-5;

        return $same ? array_merge($a, array_slice($b, 1)) : array_merge($a, $b);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<array{lat: float, lng: float}>|null  $stored
     */
    private function shouldRecalculate(array $context, float $lat, float $lng, ?array $stored): bool
    {
        if ($stored === null || $stored === []) {
            return true;
        }

        $lastLat = (float) ($context['last_nav_lat'] ?? 0);
        $lastLng = (float) ($context['last_nav_lng'] ?? 0);
        if ($lastLat === 0.0 && $lastLng === 0.0) {
            return true;
        }

        $movedKm = GeoMath::haversineKm($lastLat, $lastLng, $lat, $lng);
        $recalcKm = (float) config('routes.navigation_recalc_km', 0.8);

        return $movedKm >= $recalcKm;
    }

    /**
     * @return list<array{lat: float, lng: float}>|null
     */
    private function decodeStored(?string $encoded): ?array
    {
        if (! $encoded) {
            return null;
        }

        $vertices = PolylineDecoder::decode($encoded);

        return count($vertices) >= 2 ? $vertices : null;
    }
}
