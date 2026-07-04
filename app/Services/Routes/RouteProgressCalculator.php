<?php

namespace App\Services\Routes;

use App\Models\RouteCheckpoint;
use App\Models\RoutePlan;
use App\Support\Geo\GeoMath;

class RouteProgressCalculator
{
    public function __construct(
        private RouteGuidanceService $guidance,
        private SmartRouteMatcher $matcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(
        RoutePlan $route,
        float $lat,
        float $lng,
        ?float $speedKmh = null,
        ?float $headingDeg = null,
        ?array $navigationContext = null,
    ): array {
        $route->loadMissing('checkpoints');

        $path = $this->guidance->pathVertices($route);
        $checkpointSegments = $this->buildCheckpointSegments($route);

        $match = $this->matcher->evaluate($route, $path, $lat, $lng, $speedKmh, $headingDeg, $navigationContext);

        $totalDistanceKm = $match['total_distance_km'] > 0
            ? $match['total_distance_km']
            : $this->totalDistanceKm($route, $checkpointSegments);

        $travelledKm = min((float) $match['travelled_km'], $totalDistanceKm);
        $percentage = (float) $match['percentage_completed'];
        $remainingKm = (float) $match['remaining_distance_km'];
        $progressBarPosition = (float) $match['progress_bar_position'];
        $atDestination = (bool) $match['at_destination'];
        $onRoute = (bool) $match['on_route'];

        if ($percentage >= 99.5 && ! $atDestination && $onRoute) {
            $destDistanceM = (float) $match['distance_to_destination_m'];
            $endpointPct = $this->endpointProgressPercent($totalDistanceKm, $destDistanceM);
            $percentage = min(99, $endpointPct + 5);
            $remainingKm = max($remainingKm, $destDistanceM / 1000);
            $progressBarPosition = $percentage / 100;
        }

        $currentSegment = $this->segmentForTravelledKm($checkpointSegments, $travelledKm);

        $etaAt = null;
        $effectiveSpeed = ($speedKmh !== null && $speedKmh > 5) ? $speedKmh : null;
        if ($effectiveSpeed === null && $currentSegment['expected_duration_minutes'] > 0 && $currentSegment['distance_km'] > 0) {
            $effectiveSpeed = ($currentSegment['distance_km'] / $currentSegment['expected_duration_minutes']) * 60;
        }
        if ($effectiveSpeed === null && $route->expected_duration_minutes > 0 && $totalDistanceKm > 0) {
            $effectiveSpeed = ($totalDistanceKm / $route->expected_duration_minutes) * 60;
        }
        $canEstimateEta = in_array($match['navigation_state'], [
            SmartRouteMatcher::STATE_ON_ASSIGNED,
            SmartRouteMatcher::STATE_SLIGHT_DEVIATION,
            SmartRouteMatcher::STATE_JOINING,
            SmartRouteMatcher::STATE_RECALCULATED,
            SmartRouteMatcher::STATE_DESTINATION_REACHED,
        ], true);

        if ($effectiveSpeed !== null && $effectiveSpeed > 0 && $remainingKm > 0 && $canEstimateEta) {
            $etaMinutes = ($remainingKm / $effectiveSpeed) * 60;
            $etaAt = now()->addMinutes((int) round($etaMinutes));
        }

        return array_merge($match, [
            'current_checkpoint_sequence' => $currentSegment['sequence'],
            'current_segment_label' => $currentSegment['label'],
            'percentage_completed' => round($percentage, 2),
            'distance_travelled_km' => round($travelledKm, 3),
            'remaining_distance_km' => round($remainingKm, 3),
            'total_distance_km' => round($totalDistanceKm, 3),
            'expected_duration_minutes' => (int) $route->expected_duration_minutes,
            'eta_at' => $etaAt?->toIso8601String(),
            'eta_human' => $etaAt ? app_datetime_format($etaAt) : null,
            'estimated_arrival_time' => $etaAt ? app_datetime_format($etaAt) : null,
            'progress_bar_position' => round($progressBarPosition, 4),
            'current_speed_kmh' => $speedKmh !== null ? round($speedKmh, 1) : null,
        ]);
    }

    /**
     * Position each waypoint along the assigned route path (0 = start, 100 = destination).
     *
     * @return array<string, float>
     */
    public function milestonePositionsPct(RoutePlan $route): array
    {
        $route->loadMissing('checkpoints');
        $path = $this->guidance->pathVertices($route);

        if (count($path) < 2) {
            return $this->milestonePositionsFromSegments($route);
        }

        $totalKm = $this->matcher->projectOntoPath(
            (float) $route->destination_lat,
            (float) $route->destination_lng,
            $path,
        )['total_distance_km'];

        if ($totalKm <= 0) {
            return $this->milestonePositionsFromSegments($route);
        }

        $positions = ['start' => 0.0];

        foreach ($route->checkpoints as $checkpoint) {
            $proj = $this->matcher->projectOntoPath(
                (float) $checkpoint->to_lat,
                (float) $checkpoint->to_lng,
                $path,
            );
            $pct = ($proj['travelled_km'] / $totalKm) * 100;
            $positions['checkpoint-'.(int) $checkpoint->sequence] = round(min(99.5, max(0.5, $pct)), 2);
        }

        $positions['destination'] = 100.0;

        return $positions;
    }

    /**
     * @return array<string, float>
     */
    private function milestonePositionsFromSegments(RoutePlan $route): array
    {
        $route->loadMissing('checkpoints');
        $totalKm = (float) $route->expected_distance_km;
        if ($totalKm <= 0) {
            $totalKm = (float) $route->checkpoints->sum('distance_km');
        }
        if ($totalKm <= 0) {
            $totalKm = 1.0;
        }

        $positions = ['start' => 0.0];
        $walked = 0.0;

        foreach ($route->checkpoints as $checkpoint) {
            $walked += (float) $checkpoint->distance_km;
            $positions['checkpoint-'.(int) $checkpoint->sequence] = round(min(99.5, max(0.5, ($walked / $totalKm) * 100)), 2);
        }

        $positions['destination'] = 100.0;

        return $positions;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    private function segmentForTravelledKm(array $segments, float $travelledKm): array
    {
        $walked = 0.0;
        foreach ($segments as $segment) {
            $walked += (float) $segment['distance_km'];
            if ($travelledKm <= $walked + 0.001) {
                return $segment;
            }
        }

        return $segments[array_key_last($segments)] ?? [
            'sequence' => 0,
            'label' => '',
            'distance_km' => 0,
            'expected_duration_minutes' => 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCheckpointSegments(RoutePlan $route): array
    {
        $segments = [];
        $prevLat = (float) $route->start_lat;
        $prevLng = (float) $route->start_lng;
        $prevLabel = $route->start_city;

        /** @var RouteCheckpoint $checkpoint */
        foreach ($route->checkpoints as $checkpoint) {
            $distance = (float) $checkpoint->distance_km;
            if ($distance <= 0) {
                $distance = GeoMath::haversineKm(
                    (float) $checkpoint->from_lat,
                    (float) $checkpoint->from_lng,
                    (float) $checkpoint->to_lat,
                    (float) $checkpoint->to_lng,
                );
            }

            $segments[] = [
                'sequence' => (int) $checkpoint->sequence,
                'label' => $checkpoint->segmentLabel(),
                'from_lat' => (float) $checkpoint->from_lat,
                'from_lng' => (float) $checkpoint->from_lng,
                'to_lat' => (float) $checkpoint->to_lat,
                'to_lng' => (float) $checkpoint->to_lng,
                'distance_km' => $distance,
                'expected_duration_minutes' => (int) $checkpoint->expected_duration_minutes,
            ];

            $prevLat = (float) $checkpoint->to_lat;
            $prevLng = (float) $checkpoint->to_lng;
            $prevLabel = $checkpoint->to_location;
        }

        $finalDistance = GeoMath::haversineKm(
            $prevLat,
            $prevLng,
            (float) $route->destination_lat,
            (float) $route->destination_lng,
        );

        if ($finalDistance > 0.01) {
            $segments[] = [
                'sequence' => count($segments) + 1,
                'label' => $prevLabel.' → '.$route->destination_city,
                'from_lat' => $prevLat,
                'from_lng' => $prevLng,
                'to_lat' => (float) $route->destination_lat,
                'to_lng' => (float) $route->destination_lng,
                'distance_km' => $finalDistance,
                'expected_duration_minutes' => 0,
            ];
        }

        return $segments;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    private function totalDistanceKm(RoutePlan $route, array $segments): float
    {
        $fromSegments = array_sum(array_column($segments, 'distance_km'));
        if ($fromSegments > 0) {
            return (float) $fromSegments;
        }

        return (float) $route->expected_distance_km;
    }

    private function endpointProgressPercent(float $totalDistanceKm, float $destDistanceM): float
    {
        if ($totalDistanceKm <= 0) {
            return 0.0;
        }

        $totalM = $totalDistanceKm * 1000;
        $ratio = 1 - min(1, max(0, $destDistanceM / $totalM));

        return round($ratio * 100, 2);
    }
}
