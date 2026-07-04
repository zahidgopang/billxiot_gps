<?php

namespace App\Services\Routes;

use App\Models\RoutePlan;
use App\Support\Geo\GeoLocalityResolver;
use App\Support\Geo\GeoMath;
use App\Support\Geo\PolylineDecoder;
use App\Support\Geo\PolylineEncoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Road-following path from stored polyline or Google Directions API.
 */
class RouteGuidanceService
{
    public function __construct(
        private GeoLocalityResolver $locality,
    ) {}

    /**
     * @return list<array{lat: float, lng: float}>
     */
    public function pathVertices(RoutePlan $route): array
    {
        $route->loadMissing('checkpoints');

        $stored = $route->storedPathVertices();
        if (count($stored) >= 2) {
            return $stored;
        }

        if (! $route->show_polyline) {
            return $this->fallbackVertices($route);
        }

        $signature = $this->routeSignature($route);

        return Cache::remember(
            "route:guidance:v2:{$route->id}:{$signature}",
            now()->addDays(30),
            fn () => $this->fetchFromGoogle($route)['vertices'] ?? $this->fallbackVertices($route),
        );
    }

    public function generateAndPersistPolyline(RoutePlan $route): bool
    {
        $route->loadMissing('checkpoints');
        $this->forgetCache($route);

        if (! $route->show_polyline) {
            $route->forceFill([
                'encoded_polyline' => null,
                'guided_distance_km' => null,
                'guided_duration_minutes' => null,
                'polyline_generated_at' => null,
            ])->save();

            return true;
        }

        $result = $this->fetchFromGoogle($route);
        if ($result === null || ($result['encoded_polyline'] ?? '') === '') {
            return false;
        }

        $route->forceFill([
            'encoded_polyline' => $result['encoded_polyline'],
            'guided_distance_km' => $result['distance_km'],
            'guided_duration_minutes' => $result['duration_minutes'],
            'polyline_generated_at' => now(),
            'start_city' => $this->locality->sanitizeLabel($route->start_city) ?? $route->start_city,
            'destination_city' => $this->locality->sanitizeLabel($route->destination_city) ?? $route->destination_city,
        ])->save();

        $this->forgetCache($route);

        return true;
    }

    /**
     * Build checkpoint rows from the stored / freshly fetched driving route.
     *
     * @return list<array<string, mixed>>
     */
    public function buildCheckpointRows(RoutePlan $route, ?array $guidanceResult = null): array
    {
        $guidanceResult ??= $this->fetchFromGoogle($route);
        if ($guidanceResult === null) {
            return [];
        }

        $vertices = $guidanceResult['vertices'] ?? [];
        if (count($vertices) < 2) {
            return [];
        }

        $legs = $guidanceResult['legs'] ?? [];
        $waypoints = count($legs) > 1
            ? $this->waypointsFromLegs($route, $legs)
            : $this->sampleLocalityWaypoints($route, $vertices);

        return $this->rowsFromWaypoints($route, $waypoints, $legs);
    }

    /**
     * @param  list<array<string, mixed>>  $legs
     * @return list<array{lat: float, lng: float, label: string}>
     */
    private function waypointsFromLegs(RoutePlan $route, array $legs): array
    {
        $waypoints = [];
        $legCount = count($legs);

        foreach ($legs as $index => $leg) {
            if ($index >= $legCount - 1) {
                break;
            }

            $lat = (float) ($leg['end_location']['lat'] ?? 0);
            $lng = (float) ($leg['end_location']['lng'] ?? 0);
            if (! $lat && ! $lng) {
                continue;
            }

            $label = $this->locality->sanitizeLabel($leg['end_address'] ?? null)
                ?? $this->locality->reverseGeocode($lat, $lng)
                ?? 'Checkpoint '.($index + 1);

            $waypoints[] = ['lat' => $lat, 'lng' => $lng, 'label' => $label];
        }

        return $this->dedupeWaypoints($waypoints);
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $vertices
     * @return list<array{lat: float, lng: float, label: string}>
     */
    private function sampleLocalityWaypoints(RoutePlan $route, array $vertices): array
    {
        $cumulative = $this->cumulativeDistances($vertices);
        $totalKm = end($cumulative) ?: 0.0;
        if ($totalKm < 20) {
            return [];
        }

        $intervalKm = max(35.0, min(90.0, $totalKm / 6));
        $maxPoints = 10;
        $waypoints = [];
        $lastLabel = $this->locality->sanitizeLabel($route->start_city) ?? '';
        $nextTarget = $intervalKm;

        for ($i = 1; $i < count($vertices); $i++) {
            while ($nextTarget < $totalKm * 0.92 && $cumulative[$i] >= $nextTarget) {
                if (count($waypoints) >= $maxPoints) {
                    break 2;
                }

                $point = $vertices[$i];
                $ratio = $nextTarget / max($totalKm, 0.001);
                if ($ratio < 0.08) {
                    $nextTarget += $intervalKm;
                    continue;
                }

                $label = $this->locality->reverseGeocode($point['lat'], $point['lng']);
                if ($label !== null && strcasecmp($label, $lastLabel) !== 0) {
                    $waypoints[] = [
                        'lat' => $point['lat'],
                        'lng' => $point['lng'],
                        'label' => $label,
                    ];
                    $lastLabel = $label;
                }

                $nextTarget += $intervalKm;
            }
        }

        return $this->dedupeWaypoints($waypoints);
    }

    /**
     * @param  list<array{lat: float, lng: float, label: string}>  $waypoints
     * @param  list<array<string, mixed>>  $legs
     * @return list<array<string, mixed>>
     */
    private function rowsFromWaypoints(RoutePlan $route, array $waypoints, array $legs): array
    {
        $startCity = $this->locality->sanitizeLabel($route->start_city) ?? 'Start';
        $destCity = $this->locality->sanitizeLabel($route->destination_city) ?? 'Destination';
        $anchors = array_merge(
            [[
                'lat' => (float) $route->start_lat,
                'lng' => (float) $route->start_lng,
                'label' => $startCity,
            ]],
            $waypoints,
            [[
                'lat' => (float) $route->destination_lat,
                'lng' => (float) $route->destination_lng,
                'label' => $destCity,
            ]],
        );

        $rows = [];
        $legDurations = array_map(
            fn (array $leg) => max(1, (int) round(((int) ($leg['duration']['value'] ?? 0)) / 60)),
            $legs,
        );
        $legDistances = array_map(
            fn (array $leg) => round(((int) ($leg['distance']['value'] ?? 0)) / 1000, 2),
            $legs,
        );

        for ($i = 1; $i < count($anchors); $i++) {
            $from = $anchors[$i - 1];
            $to = $anchors[$i];
            $distance = GeoMath::haversineKm($from['lat'], $from['lng'], $to['lat'], $to['lng']);
            $duration = 0;
            $legIndex = $i - 1;
            if (isset($legDurations[$legIndex]) && $legDurations[$legIndex] > 0) {
                $duration = $legDurations[$legIndex];
                $distance = $legDistances[$legIndex] ?? $distance;
            } else            if ($distance > 0 && $route->guided_duration_minutes > 0 && $route->guided_distance_km > 0) {
                $duration = max(1, (int) round(($distance / (float) $route->guided_distance_km) * (int) $route->guided_duration_minutes));
            }

            $rows[] = [
                'sequence' => $i,
                'from_location' => (string) $from['label'],
                'to_location' => (string) $to['label'],
                'from_lat' => $from['lat'],
                'from_lng' => $from['lng'],
                'to_lat' => $to['lat'],
                'to_lng' => $to['lng'],
                'distance_km' => round($distance, 2),
                'expected_duration_minutes' => $duration,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $vertices
     * @return list<float>
     */
    private function cumulativeDistances(array $vertices): array
    {
        $cumulative = [0.0];
        for ($i = 1; $i < count($vertices); $i++) {
            $cumulative[] = $cumulative[$i - 1] + GeoMath::haversineKm(
                $vertices[$i - 1]['lat'],
                $vertices[$i - 1]['lng'],
                $vertices[$i]['lat'],
                $vertices[$i]['lng'],
            );
        }

        return $cumulative;
    }

    /**
     * @param  list<array{lat: float, lng: float, label: string}>  $waypoints
     * @return list<array{lat: float, lng: float, label: string}>
     */
    private function dedupeWaypoints(array $waypoints): array
    {
        $out = [];
        $prevLabel = null;

        foreach ($waypoints as $waypoint) {
            $label = $this->locality->sanitizeLabel($waypoint['label'] ?? null);
            if ($label === null) {
                continue;
            }
            if ($prevLabel !== null && strcasecmp($prevLabel, $label) === 0) {
                continue;
            }
            $out[] = [
                'lat' => (float) $waypoint['lat'],
                'lng' => (float) $waypoint['lng'],
                'label' => $label,
            ];
            $prevLabel = $label;
        }

        return $out;
    }

    public function persistGuidanceResult(RoutePlan $route, array $result): void
    {
        $route->forceFill([
            'encoded_polyline' => $result['encoded_polyline'],
            'guided_distance_km' => $result['distance_km'],
            'guided_duration_minutes' => $result['duration_minutes'],
            'polyline_generated_at' => now(),
            'start_city' => $this->locality->sanitizeLabel($route->start_city) ?? $route->start_city,
            'destination_city' => $this->locality->sanitizeLabel($route->destination_city) ?? $route->destination_city,
        ])->save();

        $this->forgetCache($route);
    }

    public function forgetCache(RoutePlan $route): void
    {
        $route->loadMissing('checkpoints');
        $signature = $this->routeSignature($route);
        Cache::forget("route:guidance:v2:{$route->id}:{$signature}");
        Cache::forget("route:guidance:v1:{$route->id}:{$signature}");
    }

    /**
     * @return list<array{lat: float, lng: float, label: string}>
     */
    public function checkpointMarkers(RoutePlan $route): array
    {
        $route->loadMissing('checkpoints');

        $markers = [[
            'lat' => (float) $route->start_lat,
            'lng' => (float) $route->start_lng,
            'label' => $this->locality->sanitizeLabel($route->start_city) ?? $route->start_city,
            'kind' => 'start',
        ]];

        foreach ($route->checkpoints as $checkpoint) {
            $markers[] = [
                'lat' => (float) $checkpoint->to_lat,
                'lng' => (float) $checkpoint->to_lng,
                'label' => $this->locality->sanitizeLabel($checkpoint->to_location) ?? $checkpoint->to_location,
                'kind' => 'checkpoint',
                'sequence' => (int) $checkpoint->sequence,
            ];
        }

        $markers[] = [
            'lat' => (float) $route->destination_lat,
            'lng' => (float) $route->destination_lng,
            'label' => $this->locality->sanitizeLabel($route->destination_city) ?? $route->destination_city,
            'kind' => 'destination',
        ];

        return $markers;
    }

    /**
     * Driving route from the vehicle's current position to the assigned destination.
     *
     * @return list<array{lat: float, lng: float}>|null
     */
    public function fetchDynamicRoute(RoutePlan $route, float $lat, float $lng): ?array
    {
        $result = $this->fetchNavigationRoute($route, $lat, $lng);

        return $result['vertices'] ?? null;
    }

    /**
     * @return array{vertices: list<array{lat: float, lng: float}>, distance_km: float, duration_minutes: int, encoded_polyline: string}|null
     */
    public function fetchNavigationRoute(RoutePlan $route, float $lat, float $lng): ?array
    {
        if (! $route->show_polyline) {
            return null;
        }

        $key = (string) config('services.google.maps_key');
        if ($key === '') {
            return null;
        }

        $route->loadMissing('checkpoints');
        $waypoints = $route->checkpoints
            ->map(fn ($cp) => round((float) $cp->to_lat, 6).','.round((float) $cp->to_lng, 6))
            ->filter(fn (string $pair) => $pair !== '0,0')
            ->values()
            ->all();

        $lat = round($lat, 4);
        $lng = round($lng, 4);
        $cacheMinutes = (int) config('routes.dynamic_route_cache_minutes', 5);
        $cacheKey = 'route:nav:'.$route->id.':'.$lat.':'. $lng.':'.md5(implode('|', $waypoints));

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ! empty($cached['vertices'])) {
            return $cached;
        }

        $result = $this->requestDirections(
            round($lat, 6).','.round($lng, 6),
            round((float) $route->destination_lat, 6).','.round((float) $route->destination_lng, 6),
            $waypoints,
            $key,
        );

        if ($result === null || empty($result['vertices'])) {
            return null;
        }

        $payload = [
            'vertices' => $result['vertices'],
            'distance_km' => (float) ($result['distance_km'] ?? 0),
            'duration_minutes' => (int) ($result['duration_minutes'] ?? 0),
            'encoded_polyline' => (string) ($result['encoded_polyline'] ?? PolylineEncoder::encode($result['vertices'])),
        ];

        Cache::put($cacheKey, $payload, now()->addMinutes($cacheMinutes));

        return $payload;
    }

    /**
     * @return list<array{lat: float, lng: float}>|null
     */
    public function fetchPointToPointRoute(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $key = (string) config('services.google.maps_key');
        if ($key === '') {
            return null;
        }

        $cacheKey = sprintf(
            'route:ptp:%s:%s:%s:%s',
            round($fromLat, 4),
            round($fromLng, 4),
            round($toLat, 4),
            round($toLng, 4),
        );

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('routes.dynamic_route_cache_minutes', 5)),
            function () use ($fromLat, $fromLng, $toLat, $toLng, $key) {
                $result = $this->requestDirections(
                    round($fromLat, 6).','.round($fromLng, 6),
                    round($toLat, 6).','.round($toLng, 6),
                    [],
                    $key,
                );

                return $result['vertices'] ?? null;
            },
        );
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $vertices
     * @return list<array{lat: float, lng: float}>
     */
    public function slicePathToDistance(array $vertices, float $travelledKm): array
    {
        if (count($vertices) < 2 || $travelledKm <= 0) {
            return count($vertices) ? [$vertices[0]] : [];
        }

        $result = [$vertices[0]];
        $cumulative = 0.0;

        for ($i = 0; $i < count($vertices) - 1; $i++) {
            $from = $vertices[$i];
            $to = $vertices[$i + 1];
            $segLen = GeoMath::haversineKm(
                (float) $from['lat'],
                (float) $from['lng'],
                (float) $to['lat'],
                (float) $to['lng'],
            );

            if ($cumulative + $segLen >= $travelledKm) {
                $t = ($travelledKm - $cumulative) / max($segLen, 0.00001);
                $result[] = [
                    'lat' => (float) $from['lat'] + (((float) $to['lat'] - (float) $from['lat']) * $t),
                    'lng' => (float) $from['lng'] + (((float) $to['lng'] - (float) $from['lng']) * $t),
                ];
                break;
            }

            $cumulative += $segLen;
            $result[] = $to;
        }

        return $result;
    }

    public function fetchFromGoogle(RoutePlan $route): ?array
    {
        $key = (string) config('services.google.maps_key');
        if ($key === '') {
            return null;
        }

        $route->loadMissing('checkpoints');

        $waypoints = $route->checkpoints
            ->map(fn ($cp) => round((float) $cp->to_lat, 6).','.round((float) $cp->to_lng, 6))
            ->values()
            ->all();

        return $this->requestDirections(
            round((float) $route->start_lat, 6).','.round((float) $route->start_lng, 6),
            round((float) $route->destination_lat, 6).','.round((float) $route->destination_lng, 6),
            $waypoints,
            $key,
            $route->id,
        );
    }

    /**
     * @param  list<string>  $waypoints
     * @return array{encoded_polyline: string, vertices: list<array{lat: float, lng: float}>, distance_km: float, duration_minutes: int, legs: list<array<string, mixed>>}|null
     */
    private function requestDirections(
        string $origin,
        string $destination,
        array $waypoints,
        string $key,
        ?int $routeId = null,
    ): ?array {
        $params = [
            'origin' => $origin,
            'destination' => $destination,
            'mode' => 'driving',
            'key' => $key,
        ];

        if ($waypoints !== []) {
            $params['waypoints'] = implode('|', $waypoints);
        }

        try {
            $response = Http::timeout(20)->get(
                'https://maps.googleapis.com/maps/api/directions/json',
                $params,
            );
        } catch (\Throwable $e) {
            Log::warning('Route guidance request failed', ['route_id' => $routeId, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $json = $response->json();
        if (($json['status'] ?? '') !== 'OK' || empty($json['routes'][0])) {
            Log::info('Route guidance unavailable', ['route_id' => $routeId, 'status' => $json['status'] ?? null]);

            return null;
        }

        $routeData = $json['routes'][0];
        $encoded = (string) ($routeData['overview_polyline']['points'] ?? '');
        if ($encoded === '') {
            $encoded = $this->encodeFromSteps($routeData);
        }

        if ($encoded === '') {
            return null;
        }

        $vertices = $this->dedupeVertices(PolylineDecoder::decode($encoded));
        if (count($vertices) < 2) {
            $vertices = $this->verticesFromSteps($routeData);
        }

        $distanceMeters = 0;
        $durationSeconds = 0;
        foreach ($routeData['legs'] ?? [] as $leg) {
            $distanceMeters += (int) ($leg['distance']['value'] ?? 0);
            $durationSeconds += (int) ($leg['duration']['value'] ?? 0);
        }

        return [
            'encoded_polyline' => $encoded,
            'vertices' => $vertices,
            'distance_km' => round($distanceMeters / 1000, 3),
            'duration_minutes' => max(1, (int) round($durationSeconds / 60)),
            'legs' => $routeData['legs'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $routeData
     * @return list<array{lat: float, lng: float}>
     */
    private function verticesFromSteps(array $routeData): array
    {
        $vertices = [];
        foreach ($routeData['legs'] ?? [] as $leg) {
            foreach ($leg['steps'] ?? [] as $step) {
                $stepEncoded = $step['polyline']['points'] ?? null;
                if (! is_string($stepEncoded) || $stepEncoded === '') {
                    continue;
                }
                foreach (PolylineDecoder::decode($stepEncoded) as $point) {
                    $vertices[] = $point;
                }
            }
        }

        return $this->dedupeVertices($vertices);
    }

    /**
     * @param  array<string, mixed>  $routeData
     */
    private function encodeFromSteps(array $routeData): string
    {
        $vertices = $this->verticesFromSteps($routeData);

        return $vertices === [] ? '' : PolylineEncoder::encode($vertices);
    }

    /**
     * @return list<array{lat: float, lng: float}>
     */
    private function fallbackVertices(RoutePlan $route): array
    {
        return array_map(
            fn (array $point) => ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']],
            $route->polylineVertices(),
        );
    }

    private function routeSignature(RoutePlan $route): string
    {
        return md5(json_encode([
            $route->start_lat,
            $route->start_lng,
            $route->destination_lat,
            $route->destination_lng,
            $route->checkpoints->map(fn ($cp) => [
                (float) $cp->to_lat,
                (float) $cp->to_lng,
            ])->values()->all(),
        ]));
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $vertices
     * @return list<array{lat: float, lng: float}>
     */
    private function dedupeVertices(array $vertices): array
    {
        $out = [];
        $prev = null;

        foreach ($vertices as $vertex) {
            if ($prev !== null
                && abs($prev['lat'] - $vertex['lat']) < 0.000001
                && abs($prev['lng'] - $vertex['lng']) < 0.000001) {
                continue;
            }
            $out[] = $vertex;
            $prev = $vertex;
        }

        return $out;
    }
}
