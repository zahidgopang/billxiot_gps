<?php

namespace App\Services\Routes;

use App\Models\RoutePlan;
use App\Support\Geo\GeoMath;
use Carbon\Carbon;

/**
 * Google Maps–style route matching: tolerance corridor, heading checks,
 * destination trend, and debounced off-route confirmation.
 */
class SmartRouteMatcher
{
    public const STATE_ON_ASSIGNED = 'on_assigned';

    public const STATE_JOINING = 'joining_assigned';

    public const STATE_RECALCULATED = 'route_recalculated';

    public const STATE_SLIGHT_DEVIATION = 'slight_deviation';

    public const STATE_OFF_ROUTE = 'off_route';

    public const STATE_GPS_LOST = 'gps_lost';

    public const STATE_DESTINATION_REACHED = 'destination_reached';

    /**
     * @param  list<array{lat: float, lng: float}>  $path
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    public function evaluate(
        RoutePlan $route,
        array $path,
        float $lat,
        float $lng,
        ?float $speedKmh,
        ?float $headingDeg,
        ?array $context = null,
    ): array {
        $context ??= [];
        $toleranceM = $this->toleranceMeters($route);
        $slightM = (int) config('routes.slight_deviation_meters', 500);
        $joinM = (int) config('routes.join_route_meters', 1500);
        $headingTol = (int) config('routes.heading_tolerance_degrees', 65);

        $projection = $this->projectOntoPath($lat, $lng, $path);
        $crossTrackM = $projection['cross_track_km'] * 1000;
        $travelledKm = $projection['travelled_km'];
        $totalKm = $projection['total_distance_km'];
        $routeHeading = $projection['segment_bearing_deg'];

        $destDistM = GeoMath::haversineKm(
            $lat,
            $lng,
            (float) $route->destination_lat,
            (float) $route->destination_lng,
        ) * 1000;

        $startDistM = GeoMath::haversineKm(
            $lat,
            $lng,
            (float) $route->start_lat,
            (float) $route->start_lng,
        ) * 1000;

        $bearingToDest = $this->bearingDeg($lat, $lng, (float) $route->destination_lat, (float) $route->destination_lng);
        $prevDestDistM = isset($context['last_dest_distance_m']) ? (float) $context['last_dest_distance_m'] : null;
        $movingTowardDest = $prevDestDistM === null || $destDistM < ($prevDestDistM - 40);
        $destDistIncreasing = $prevDestDistM !== null && $destDistM > ($prevDestDistM + 80);
        $isMoving = ($speedKmh ?? 0) > 3;

        $headingOk = $this->headingMatches(
            $headingDeg,
            $routeHeading,
            $bearingToDest,
            $headingTol,
            $isMoving,
        );

        $plannedTotalKm = $this->plannedTotalKm($route, $totalKm);
        if ($plannedTotalKm > 0) {
            $totalKm = $plannedTotalKm;
        }

        $snappedPct = $totalKm > 0 ? min(100, max(0, ($travelledKm / $totalKm) * 100)) : 0;
        $destProgressPct = $totalKm > 0
            ? min(100, max(0, (1 - ($destDistM / 1000) / $totalKm) * 100))
            : 0;

        $nearDestination = $destDistM <= (int) $route->arrival_radius_meters;
        $atDestination = $nearDestination
            && ($crossTrackM <= $joinM || $snappedPct >= 75);

        $state = self::STATE_ON_ASSIGNED;
        $onRoute = true;
        $progressReliable = true;

        if (! empty($context['trip_start_joining']) && $crossTrackM > $toleranceM && ! $atDestination) {
            $state = self::STATE_JOINING;
        } elseif ($atDestination) {
            $state = self::STATE_DESTINATION_REACHED;
        } elseif ($crossTrackM <= $toleranceM) {
            $state = self::STATE_ON_ASSIGNED;
        } elseif ($crossTrackM <= $slightM && ($movingTowardDest || ! $isMoving) && ($headingOk || ! $isMoving)) {
            $state = self::STATE_SLIGHT_DEVIATION;
        } elseif ($crossTrackM <= $joinM && $movingTowardDest) {
            $state = self::STATE_JOINING;
        } elseif ($movingTowardDest && $headingOk && ! $destDistIncreasing) {
            $state = self::STATE_RECALCULATED;
            $onRoute = false;
        } elseif ($this->isOffRouteCandidate($crossTrackM, $slightM, $destDistIncreasing, $movingTowardDest, $headingOk)) {
            if ($this->shouldConfirmOffRoute($crossTrackM, $slightM, $destDistIncreasing, $movingTowardDest, $headingOk, $context)) {
                $state = self::STATE_OFF_ROUTE;
                $onRoute = false;
                $progressReliable = false;
            } else {
                $state = self::STATE_RECALCULATED;
                $onRoute = false;
            }
        } elseif ($movingTowardDest) {
            $state = self::STATE_JOINING;
        } else {
            $state = self::STATE_SLIGHT_DEVIATION;
        }

        $percentage = $this->resolvePercentage(
            $state,
            $snappedPct,
            $destProgressPct,
            $context,
            $atDestination,
        );

        if ($atDestination) {
            $percentage = 100;
            $travelledKm = $totalKm;
        } elseif (in_array($state, [self::STATE_JOINING, self::STATE_RECALCULATED], true)) {
            $travelledKm = max($travelledKm, ($percentage / 100) * $totalKm);
        } elseif ($state === self::STATE_OFF_ROUTE) {
            $travelledKm = (float) ($context['last_good_travelled_km'] ?? 0);
        }

        $remainingKm = max(0, $totalKm - $travelledKm);
        if ($atDestination) {
            $remainingKm = 0;
            $travelledKm = $totalKm;
            $percentage = 100;
        } elseif ($state === self::STATE_OFF_ROUTE) {
            $remainingKm = max($remainingKm, $destDistM / 1000);
        }

        [$travelledKm, $remainingKm, $percentage] = $this->reconcileMetrics(
            $totalKm,
            $travelledKm,
            $remainingKm,
            $percentage,
            $atDestination,
        );

        $offRouteAlert = $state === self::STATE_OFF_ROUTE;
        $needsDynamicRoute = in_array($state, [
            self::STATE_JOINING,
            self::STATE_RECALCULATED,
            self::STATE_OFF_ROUTE,
        ], true);

        $nextContext = $this->buildNextContext(
            $context,
            $state,
            $destDistM,
            $crossTrackM,
            $startDistM,
            $percentage,
            $travelledKm,
            $lat,
            $lng,
        );

        return [
            'navigation_state' => $state,
            'navigation_state_label' => (string) __('app.routes.navigation_state_'.$state),
            'cross_track_km' => round($crossTrackM / 1000, 3),
            'cross_track_m' => round($crossTrackM, 1),
            'corridor_radius_meters' => $toleranceM,
            'travelled_km' => round($travelledKm, 3),
            'total_distance_km' => round($totalKm, 3),
            'remaining_distance_km' => round($remainingKm, 3),
            'percentage_completed' => round($percentage, 2),
            'progress_bar_position' => round(min(1, max(0, $percentage / 100)), 4),
            'distance_to_destination_m' => round($destDistM, 1),
            'distance_from_start_m' => round($startDistM, 1),
            'on_route' => $onRoute,
            'off_route_alert' => $offRouteAlert,
            'progress_unreliable' => ! $progressReliable,
            'progress_frozen' => $state === self::STATE_OFF_ROUTE && ($context['last_good_percentage'] ?? 0) > 0,
            'progress_frozen_label' => $state === self::STATE_OFF_ROUTE
                ? (string) __('app.routes.progress_frozen_off_route')
                : null,
            'progress_unreliable_label' => ! $progressReliable
                ? (string) __('app.routes.progress_off_route_zero')
                : null,
            'rejoin_route_hint' => $needsDynamicRoute
                ? (string) __('app.routes.rejoin_assigned_route')
                : null,
            'needs_dynamic_route' => $needsDynamicRoute,
            'at_destination' => $atDestination,
            'moving_toward_destination' => $movingTowardDest,
            'heading_matches_route' => $headingOk,
            'route_segment_bearing_deg' => $routeHeading,
            'bearing_to_destination_deg' => round($bearingToDest, 1),
            'snapped_progress_pct' => round($snappedPct, 2),
            'navigation_context' => $nextContext,
        ];
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $path
     * @return array{travelled_km: float, cross_track_km: float, total_distance_km: float, segment_bearing_deg: ?float}
     */
    public function projectOntoPath(float $lat, float $lng, array $path): array
    {
        if (count($path) < 2) {
            return [
                'travelled_km' => 0.0,
                'cross_track_km' => PHP_FLOAT_MAX,
                'total_distance_km' => 0.0,
                'segment_bearing_deg' => null,
            ];
        }

        $bestCrossTrack = PHP_FLOAT_MAX;
        $bestTravelled = 0.0;
        $cumulative = 0.0;
        $total = 0.0;
        $bestBearing = null;

        for ($i = 0; $i < count($path) - 1; $i++) {
            $from = $path[$i];
            $to = $path[$i + 1];
            $segLen = GeoMath::haversineKm(
                (float) $from['lat'],
                (float) $from['lng'],
                (float) $to['lat'],
                (float) $to['lng'],
            );
            $total += $segLen;

            $projection = GeoMath::projectOntoSegment(
                $lat,
                $lng,
                (float) $from['lat'],
                (float) $from['lng'],
                (float) $to['lat'],
                (float) $to['lng'],
            );

            if ($projection['distance_km'] < $bestCrossTrack) {
                $bestCrossTrack = $projection['distance_km'];
                $bestTravelled = $cumulative + ($segLen * ($projection['t'] ?? 0));
                $bestBearing = $this->bearingDeg(
                    (float) $from['lat'],
                    (float) $from['lng'],
                    (float) $to['lat'],
                    (float) $to['lng'],
                );
            }

            $cumulative += $segLen;
        }

        return [
            'travelled_km' => $bestTravelled,
            'cross_track_km' => $bestCrossTrack,
            'total_distance_km' => $total,
            'segment_bearing_deg' => $bestBearing,
        ];
    }

    /**
     * Mark checkpoints passed on the path without visiting as skipped.
     *
     * @param  list<array<string, mixed>>  $milestones
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function applySkippedCheckpoints(array $milestones, float $travelledKm, float $totalKm, array $context): array
    {
        $skipped = $context['skipped_checkpoints'] ?? [];
        $bufferKm = (float) config('routes.checkpoint_skip_buffer_km', 8);

        foreach ($milestones as &$milestone) {
            if (($milestone['kind'] ?? '') !== 'checkpoint') {
                continue;
            }
            $seq = (int) ($milestone['sequence'] ?? 0);
            if (in_array($seq, $skipped, true)) {
                $milestone['status'] = 'skipped';

                continue;
            }
            $positionPct = (float) ($milestone['position_pct'] ?? 50);
            $positionKm = ($positionPct / 100) * $totalKm;
            if ($travelledKm > $positionKm + $bufferKm && ($milestone['status'] ?? '') === 'pending') {
                $milestone['status'] = 'skipped';
            }
        }
        unset($milestone);

        return $milestones;
    }

    private function toleranceMeters(RoutePlan $route): int
    {
        $configured = (int) config('routes.tolerance_meters', 200);
        $arrival = (int) $route->arrival_radius_meters;

        return max(100, min(300, max($configured, min($arrival, 300))));
    }

    private function headingMatches(
        ?float $headingDeg,
        ?float $routeBearing,
        float $bearingToDest,
        int $toleranceDeg,
        bool $isMoving,
    ): bool {
        if (! $isMoving || $headingDeg === null) {
            return true;
        }

        if ($routeBearing !== null && $this->angleDelta($headingDeg, $routeBearing) <= $toleranceDeg) {
            return true;
        }

        return $this->angleDelta($headingDeg, $bearingToDest) <= ($toleranceDeg + 15);
    }

    private function isOffRouteCandidate(
        float $crossTrackM,
        int $slightM,
        bool $destDistIncreasing,
        bool $movingTowardDest,
        bool $headingOk,
    ): bool {
        if ($crossTrackM <= $slightM * 2) {
            return false;
        }

        if ($movingTowardDest && $headingOk) {
            return false;
        }

        return $destDistIncreasing || $crossTrackM > $slightM * 3;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function shouldConfirmOffRoute(
        float $crossTrackM,
        int $slightM,
        bool $destDistIncreasing,
        bool $movingTowardDest,
        bool $headingOk,
        array $context,
    ): bool {
        if ($crossTrackM <= $slightM * 2) {
            return false;
        }

        if ($movingTowardDest && $headingOk) {
            return false;
        }

        if (! $destDistIncreasing && $crossTrackM <= $slightM * 3) {
            return false;
        }

        $offSince = $context['off_route_since'] ?? $context['off_route_candidate_since'] ?? null;
        if ($offSince === null) {
            return false;
        }

        $since = Carbon::parse($offSince);
        $confirmMinutes = (int) config('routes.off_route_confirm_minutes', 3);
        $confirmKm = (float) config('routes.off_route_confirm_km', 2);
        $offKm = (float) ($context['off_route_distance_km'] ?? 0);

        return $since->diffInMinutes(now()) >= $confirmMinutes || $offKm >= $confirmKm;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolvePercentage(
        string $state,
        float $snappedPct,
        float $destProgressPct,
        array $context,
        bool $atDestination,
    ): float {
        if ($atDestination) {
            return 100;
        }

        return match ($state) {
            self::STATE_ON_ASSIGNED, self::STATE_SLIGHT_DEVIATION => $snappedPct,
            self::STATE_JOINING, self::STATE_RECALCULATED => max($snappedPct, $destProgressPct),
            self::STATE_OFF_ROUTE => (float) ($context['last_good_percentage'] ?? 0),
            default => $snappedPct,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildNextContext(
        array $context,
        string $state,
        float $destDistM,
        float $crossTrackM,
        float $startDistM,
        float $percentage,
        float $travelledKm,
        float $lat,
        float $lng,
    ): array {
        $next = $context;
        $next['last_dest_distance_m'] = $destDistM;
        $next['last_lat'] = $lat;
        $next['last_lng'] = $lng;
        $next['last_cross_track_m'] = $crossTrackM;
        $next['last_cross_track_km'] = $crossTrackM / 1000;
        $next['last_distance_from_start_m'] = $startDistM;
        $next['last_state'] = $state;

        if ($state !== self::STATE_OFF_ROUTE) {
            $next['last_good_percentage'] = $percentage;
            $next['last_good_travelled_km'] = $travelledKm;
            if ($state === self::STATE_ON_ASSIGNED || $state === self::STATE_SLIGHT_DEVIATION) {
                $next['trip_start_joining'] = false;
                $next['off_route_since'] = null;
                $next['off_route_candidate_since'] = null;
                $next['off_route_distance_km'] = 0;
            }
        } else {
            if (empty($next['off_route_since'])) {
                $next['off_route_since'] = $next['off_route_candidate_since'] ?? now()->toIso8601String();
                $next['off_route_start_lat'] = $lat;
                $next['off_route_start_lng'] = $lng;
            }
            $startLat = (float) ($next['off_route_start_lat'] ?? $lat);
            $startLng = (float) ($next['off_route_start_lng'] ?? $lng);
            $next['off_route_distance_km'] = GeoMath::haversineKm($startLat, $startLng, $lat, $lng);
        }

        $slightM = (int) config('routes.slight_deviation_meters', 500);
        if (in_array($state, [self::STATE_RECALCULATED], true) && $crossTrackM > $slightM * 2) {
            if (empty($next['off_route_candidate_since'])) {
                $next['off_route_candidate_since'] = now()->toIso8601String();
            }
        } elseif (in_array($state, [self::STATE_ON_ASSIGNED, self::STATE_JOINING, self::STATE_SLIGHT_DEVIATION], true)) {
            $next['off_route_candidate_since'] = null;
        }

        return $next;
    }

    private function bearingDeg(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1r = deg2rad($lat1);
        $lat2r = deg2rad($lat2);
        $dLng = deg2rad($lng2 - $lng1);
        $y = sin($dLng) * cos($lat2r);
        $x = cos($lat1r) * sin($lat2r) - sin($lat1r) * cos($lat2r) * cos($dLng);
        $bearing = rad2deg(atan2($y, $x));

        return fmod($bearing + 360, 360);
    }

    private function angleDelta(float $a, float $b): float
    {
        $diff = abs($a - $b) % 360;

        return $diff > 180 ? 360 - $diff : $diff;
    }

    private function plannedTotalKm(RoutePlan $route, float $pathTotalKm): float
    {
        $planned = (float) ($route->guided_distance_km ?: $route->expected_distance_km);
        if ($planned <= 0) {
            return $pathTotalKm;
        }
        if ($pathTotalKm <= 0) {
            return $planned;
        }
        if (abs($pathTotalKm - $planned) / $planned > 0.2) {
            return $planned;
        }

        return $pathTotalKm;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function reconcileMetrics(
        float $totalKm,
        float $travelledKm,
        float $remainingKm,
        float $percentage,
        bool $atDestination,
    ): array {
        if ($totalKm <= 0) {
            return [$travelledKm, $remainingKm, $percentage];
        }

        $travelledKm = min(max(0, $travelledKm), $totalKm);
        $remainingKm = max(0, $totalKm - $travelledKm);
        $percentage = min(100, max(0, ($travelledKm / $totalKm) * 100));

        if ($atDestination) {
            $travelledKm = $totalKm;
            $remainingKm = 0;
            $percentage = 100;
        }

        return [$travelledKm, $remainingKm, $percentage];
    }
}
