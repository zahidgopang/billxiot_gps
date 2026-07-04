<?php

namespace App\Services\Routes;

use App\Models\Device;
use App\Models\DeviceRouteAssignment;
use App\Models\RoutePlan;
use App\Models\TripLog;
use App\Models\User;
use App\Services\Push\PushNotificationDispatcher;
use App\Support\Geo\GeoLocalityResolver;
use App\Support\Geo\GeoMath;
use App\Support\Geo\PolylineDecoder;
use Illuminate\Support\Facades\DB;

class TripManagementService
{
    public function __construct(
        private RouteProgressCalculator $progressCalculator,
        private RouteGuidanceService $guidance,
        private PushNotificationDispatcher $pushDispatcher,
        private ActualRouteRecorder $actualRouteRecorder,
        private TripNavigationService $tripNavigation,
        private SmartRouteMatcher $routeMatcher,
    ) {}

    public function assignmentForDevice(int $deviceId): ?DeviceRouteAssignment
    {
        return DeviceRouteAssignment::query()
            ->with(['route.checkpoints'])
            ->where('device_id', $deviceId)
            ->first();
    }

    public function activeTrip(int $deviceId): ?TripLog
    {
        return TripLog::query()
            ->where('device_id', $deviceId)
            ->whereIn('status', [
                TripLog::STATUS_IN_PROGRESS,
                TripLog::STATUS_REACHED_DESTINATION,
            ])
            ->latest('id')
            ->first();
    }

    public function tripForRoute(int $deviceId, int $routeId): ?TripLog
    {
        return TripLog::query()
            ->where('device_id', $deviceId)
            ->where('route_id', $routeId)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payloadForDevice(
        Device $device,
        ?float $lat = null,
        ?float $lng = null,
        ?float $speedKmh = null,
        ?float $headingDeg = null,
        bool $positionIsLive = true,
    ): ?array {
        $assignment = $this->assignmentForDevice($device->id);
        if (! $assignment?->route?->isActive()) {
            return null;
        }

        $route = $assignment->route;
        $trip = $this->tripForRoute($device->id, $route->id);

        if ($trip?->isTerminal()) {
            return $this->buildPayload(
                $route,
                $trip,
                $this->tripProgressSnapshot($trip, $route),
                $lat,
                $lng,
                false,
            );
        }

        if (! $trip || ! $trip->isSessionActive()) {
            return $this->buildPayload($route, $trip, null, $lat, $lng, false);
        }

        if ($lat !== null && $lng !== null && $positionIsLive) {
            $trip = $this->syncTripFromPosition($device, $route, $lat, $lng, $speedKmh, $headingDeg, $trip);
        }

        $navContext = is_array($trip?->navigation_context) ? $trip->navigation_context : [];

        $progress = $this->isWaitingForStart($trip)
            ? $this->tripProgressSnapshot($trip, $route)
            : (($lat !== null && $lng !== null)
            ? $this->progressCalculator->calculate($route, $lat, $lng, $speedKmh, $headingDeg, $navContext)
            : $this->tripProgressSnapshot($trip, $route));

        if ($progress) {
            $progress['position_is_live'] = $positionIsLive;
            if (! $positionIsLive) {
                $progress['navigation_state'] = SmartRouteMatcher::STATE_GPS_LOST;
                $progress['navigation_state_label'] = (string) __('app.routes.navigation_state_gps_lost');
            }
        }

        return $this->buildPayload($route, $trip, $progress, $lat, $lng, true);
    }

    public function markCompleted(Device $device, ?User $actor = null): TripLog
    {
        $assignment = $this->assignmentForDevice($device->id);
        if (! $assignment) {
            throw new \InvalidArgumentException('Device has no assigned route.');
        }

        return DB::transaction(function () use ($device, $assignment, $actor) {
            $trip = $this->activeTrip($device->id);
            if (! $trip?->isSessionActive()) {
                throw new \InvalidArgumentException('No active trip to complete.');
            }

            $trip->status = TripLog::STATUS_COMPLETED;
            $trip->completed_at = now();
            if (! $trip->reached_destination_at) {
                $trip->reached_destination_at = now();
            }
            $trip->percentage_completed = 100;
            $trip->remaining_distance_km = 0;
            $trip->trip_summary = $this->buildTripSummary($trip, $assignment->route);
            $trip->save();

            $this->notifyTripCompleted($device, $assignment->route, $trip);

            return $trip->fresh(['route']);
        });
    }

    public function startNewTrip(
        Device $device,
        ?float $lat = null,
        ?float $lng = null,
        ?float $speedKmh = null,
        ?User $actor = null,
    ): TripLog {
        return $this->startTrip($device, $lat, $lng, $speedKmh, $actor);
    }

    public function startTrip(
        Device $device,
        ?float $lat = null,
        ?float $lng = null,
        ?float $speedKmh = null,
        ?User $actor = null,
    ): TripLog {
        $assignment = $this->assignmentForDevice($device->id);
        if (! $assignment) {
            throw new \InvalidArgumentException('Device has no assigned route.');
        }

        if (! $assignment->route?->isActive()) {
            throw new \InvalidArgumentException('Assigned route is not active.');
        }

        $active = $this->activeTrip($device->id);
        if ($active?->isSessionActive()) {
            throw new \InvalidArgumentException('A trip is already in progress. Use restart trip instead.');
        }

        return DB::transaction(function () use ($device, $assignment, $lat, $lng, $speedKmh) {
            $route = $assignment->route;
            $expectedKm = (float) ($route->guided_distance_km ?: $route->expected_distance_km);
            $startsInsideGate = $lat !== null
                && $lng !== null
                && $this->hasReachedStartArea($route, $lat, $lng);

            $trip = TripLog::query()->create([
                'device_id' => $device->id,
                'route_id' => $route->id,
                'status' => TripLog::STATUS_IN_PROGRESS,
                'started_at' => $startsInsideGate ? now() : null,
                'start_lat' => $startsInsideGate ? $lat : null,
                'start_lng' => $startsInsideGate ? $lng : null,
                'percentage_completed' => 0,
                'distance_travelled_km' => 0,
                'corridor_progress_km' => 0,
                'actual_distance_km' => 0,
                'extra_distance_km' => 0,
                'expected_distance_km' => $expectedKm,
                'remaining_distance_km' => $expectedKm,
                'trip_statistics' => $this->actualRouteRecorder->defaultStatistics(),
                'navigation_context' => $this->initialNavigationContext($route, $lat, $lng),
            ]);

            if ($startsInsideGate && $lat !== null && $lng !== null) {
                $this->tripNavigation->resolve($trip, $route, $lat, $lng, true);
                $trip = $this->syncTripFromPosition($device, $route, $lat, $lng, $speedKmh, null, $trip);
            }

            return $trip->fresh(['route']);
        });
    }

    public function restartTrip(
        Device $device,
        ?float $lat = null,
        ?float $lng = null,
        ?float $speedKmh = null,
        ?User $actor = null,
    ): TripLog {
        $assignment = $this->assignmentForDevice($device->id);
        if (! $assignment) {
            throw new \InvalidArgumentException('Device has no assigned route.');
        }

        return DB::transaction(function () use ($device, $lat, $lng, $speedKmh, $actor) {
            $active = $this->activeTrip($device->id);
            if ($active?->isSessionActive()) {
                $active->status = TripLog::STATUS_CANCELLED;
                $active->completed_at = now();
                $active->save();
            }

            return $this->startTrip($device, $lat, $lng, $speedKmh, $actor);
        });
    }

    private function syncTripFromPosition(
        Device $device,
        RoutePlan $route,
        float $lat,
        float $lng,
        ?float $speedKmh,
        ?float $headingDeg,
        ?TripLog $trip
    ): TripLog {
        if (! $trip || ! $trip->isSessionActive()) {
            throw new \InvalidArgumentException('Trip session is not active.');
        }

        if ($this->isWaitingForStart($trip)) {
            if (! $this->hasReachedStartArea($route, $lat, $lng)) {
                return $this->syncWaitingForStart($route, $lat, $lng, $speedKmh, $headingDeg, $trip);
            }

            $trip = $this->activateTripStart($trip, $lat, $lng);
        }

        $trip = $this->actualRouteRecorder->record($trip, $lat, $lng, $speedKmh, null, true);

        $forceNav = ! empty($trip->navigation_context['trip_start_joining'])
            || in_array($trip->navigation_context['last_state'] ?? '', [
                SmartRouteMatcher::STATE_OFF_ROUTE,
                SmartRouteMatcher::STATE_RECALCULATED,
                SmartRouteMatcher::STATE_JOINING,
            ], true);

        $this->tripNavigation->resolve($trip, $route, $lat, $lng, $forceNav);

        $navContext = is_array($trip->navigation_context) ? $trip->navigation_context : [];
        $progress = $this->progressCalculator->calculate($route, $lat, $lng, $speedKmh, $headingDeg, $navContext);
        $progress = $this->finalizeProgress($progress, $route, $trip);
        $progress['actual_distance_km'] = (float) $trip->actual_distance_km;
        $progress['extra_distance_km'] = (float) $trip->extra_distance_km;
        $progress['expected_distance_km'] = (float) ($trip->expected_distance_km ?? $progress['total_distance_km'] ?? 0);
        $progress['corridor_progress_km'] = (float) ($progress['distance_travelled_km'] ?? 0);
        $progress['remaining_distance_km'] = (float) ($trip->remaining_distance_km ?? $progress['remaining_distance_km'] ?? 0);

        if ($trip->isTerminal()) {
            return $trip;
        }

        $status = $this->resolveStatus($route, $progress, $trip);

        if ($status === TripLog::STATUS_IN_PROGRESS && ! $trip->started_at) {
            $trip->started_at = now();
        }

        $previousSequence = (int) ($trip->current_checkpoint_sequence ?? 0);
        $this->syncCheckpointTimings($trip, $progress, $previousSequence);

        if ($status === TripLog::STATUS_REACHED_DESTINATION && ! $trip->reached_destination_at) {
            $trip->reached_destination_at = now();
            $this->recordFinalSegmentTiming($trip, $route, $progress);
            if ($route->auto_complete_on_arrival) {
                $status = TripLog::STATUS_COMPLETED;
                $trip->completed_at = now();
                $this->notifyTripCompleted($device, $route, $trip);
            }
        }

        $navContext = $progress['navigation_context'] ?? [];
        $navContext = $this->syncSkippedCheckpoints($route, $progress, $navContext);

        $trip->fill([
            'current_checkpoint_sequence' => $progress['current_checkpoint_sequence'],
            'current_segment_label' => $progress['current_segment_label'],
            'distance_travelled_km' => $progress['actual_distance_km'] ?? $progress['distance_travelled_km'],
            'corridor_progress_km' => $progress['corridor_progress_km'] ?? $progress['distance_travelled_km'],
            'remaining_distance_km' => $progress['remaining_distance_km'],
            'eta_at' => $progress['eta_at']
                ? \Carbon\Carbon::parse($progress['eta_at'])
                : $trip->eta_at,
            'status' => $status,
            'last_lat' => $lat,
            'last_lng' => $lng,
            'navigation_context' => $navContext,
            'percentage_completed' => $progress['percentage_completed'],
            'extra_distance_km' => $progress['extra_distance_km'] ?? $trip->extra_distance_km,
        ]);

        $trip->save();

        return $trip;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function resolveStatus(RoutePlan $route, array $progress, TripLog $trip): string
    {
        if ($trip->status === TripLog::STATUS_COMPLETED) {
            return TripLog::STATUS_COMPLETED;
        }

        if (($progress['at_destination'] ?? false)
            || ($progress['distance_to_destination_m'] ?? PHP_FLOAT_MAX) <= $route->arrival_radius_meters) {
            return TripLog::STATUS_REACHED_DESTINATION;
        }

        if (($progress['percentage_completed'] ?? 0) >= 0.5
            || ($progress['distance_from_start_m'] ?? 0) > $route->arrival_radius_meters
            || in_array($progress['navigation_state'] ?? '', [
                SmartRouteMatcher::STATE_ON_ASSIGNED,
                SmartRouteMatcher::STATE_JOINING,
                SmartRouteMatcher::STATE_SLIGHT_DEVIATION,
                SmartRouteMatcher::STATE_RECALCULATED,
            ], true)) {
            return TripLog::STATUS_IN_PROGRESS;
        }

        return TripLog::STATUS_NOT_STARTED;
    }

    /**
     * @param  array<string, mixed>  $progress
     * @return array<string, mixed>
     */
    private function finalizeProgress(array $progress, RoutePlan $route, ?TripLog $trip = null): array
    {
        $navContext = is_array($trip?->navigation_context)
            ? $trip->navigation_context
            : ($progress['navigation_context'] ?? []);

        $progress = $this->reconcileProgressNumbers($progress, $route);

        return $this->applyNavigationDisplayRules($progress, $route, $trip, $navContext);
    }

    /**
     * Progress only counts on the assigned corridor. Off-route / unverified states show 0% unless
     * we can freeze a verified last on-route percentage.
     *
     * @param  array<string, mixed>  $progress
     * @param  array<string, mixed>  $navContext
     * @return array<string, mixed>
     */
    private function applyNavigationDisplayRules(
        array $progress,
        RoutePlan $route,
        ?TripLog $trip,
        array $navContext,
    ): array {
        $state = (string) ($progress['navigation_state'] ?? SmartRouteMatcher::STATE_ON_ASSIGNED);

        if ($trip !== null && $this->isWaitingForStart($trip)) {
            $total = (float) ($progress['total_distance_km'] ?? 0);
            if ($total <= 0) {
                $total = (float) ($route->guided_distance_km ?: $route->expected_distance_km);
            }

            $progress['progress_counting'] = false;
            $progress['progress_frozen'] = false;
            $progress['progress_unreliable'] = false;
            $progress['progress_waiting_for_start'] = true;
            $progress['waiting_for_start'] = true;
            $progress['percentage_completed'] = 0;
            $progress['distance_travelled_km'] = 0;
            $progress['corridor_progress_km'] = 0;
            $progress['remaining_distance_km'] = round(max(0, $total), 3);
            $progress['progress_bar_position'] = 0;
            $progress['navigation_state'] = SmartRouteMatcher::STATE_JOINING;
            $progress['navigation_state_label'] = (string) __('app.routes.trip_waiting_for_start');
            $progress['progress_unreliable_label'] = (string) __('app.routes.trip_waiting_for_start_hint');
            $progress['start_radius_meters'] = $this->startRadiusMeters($route);

            return $progress;
        }

        if ($this->navigationStateCountsProgress($state, $progress)) {
            $progress['progress_counting'] = true;
            $progress['progress_frozen'] = false;
            $progress['progress_unreliable'] = false;

            return $progress;
        }

        $progress['progress_counting'] = false;
        $progress['off_route_alert'] = $state === SmartRouteMatcher::STATE_OFF_ROUTE;

        $lastGood = (float) ($navContext['last_good_percentage'] ?? 0);
        $canFreeze = $trip !== null
            && $lastGood > 0
            && $this->canFreezeStoredProgressOffRoute($progress, $trip, $route, $lastGood);

        if ($canFreeze) {
            $total = (float) ($progress['total_distance_km'] ?? 0);
            $traveled = (float) ($navContext['last_good_travelled_km'] ?? $trip->distance_travelled_km ?? 0);

            $progress['progress_frozen'] = true;
            $progress['progress_unreliable'] = false;
            $progress['percentage_completed'] = $lastGood;
            $progress['distance_travelled_km'] = $traveled;
            $progress['remaining_distance_km'] = $total > 0
                ? max(0, $total - $traveled)
                : (float) ($trip->remaining_distance_km ?? 0);
            $progress['progress_bar_position'] = min(1, max(0, $lastGood / 100));
            $progress['progress_frozen_label'] = (string) __('app.routes.progress_frozen_off_route');

            return $progress;
        }

        $total = (float) ($progress['total_distance_km'] ?? 0);

        $progress['progress_frozen'] = false;
        $progress['progress_unreliable'] = true;
        $progress['percentage_completed'] = 0;
        $progress['distance_travelled_km'] = 0;
        $progress['progress_bar_position'] = 0;
        $progress['remaining_distance_km'] = $total > 0
            ? $total
            : max((float) ($progress['remaining_distance_km'] ?? 0), ((float) ($progress['distance_to_destination_m'] ?? 0)) / 1000);
        $progress['progress_unreliable_label'] = (string) __('app.routes.progress_off_route_zero');

        return $progress;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function navigationStateCountsProgress(string $state, array $progress): bool
    {
        if (in_array($state, [
            SmartRouteMatcher::STATE_ON_ASSIGNED,
            SmartRouteMatcher::STATE_SLIGHT_DEVIATION,
            SmartRouteMatcher::STATE_DESTINATION_REACHED,
        ], true)) {
            return true;
        }

        if (in_array($state, [
            SmartRouteMatcher::STATE_JOINING,
            SmartRouteMatcher::STATE_RECALCULATED,
        ], true)) {
            return (bool) ($progress['on_route'] ?? false);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function canFreezeStoredProgressOffRoute(
        array $progress,
        TripLog $trip,
        RoutePlan $route,
        ?float $verifiedPct = null,
    ): bool {
        $verified = $verifiedPct ?? (float) ($trip->percentage_completed ?? 0);
        if ($verified <= 0) {
            return false;
        }

        if (! isset($progress['cross_track_km']) && ! isset($progress['distance_from_start_m'])) {
            return false;
        }

        $corridorMeters = max(2500, (int) ($progress['corridor_radius_meters'] ?? $route->arrival_radius_meters));
        $crossTrackMeters = (float) ($progress['cross_track_km'] ?? 0) * 1000;

        if ($crossTrackMeters > max(10000, $corridorMeters * 4)) {
            return false;
        }

        $totalKm = (float) ($progress['total_distance_km'] ?? $route->expected_distance_km);
        if ($totalKm <= 0) {
            return false;
        }

        $expectedAlongKm = ($verified / 100) * $totalKm;
        $actualFromStartKm = ((float) ($progress['distance_from_start_m'] ?? 0)) / 1000;
        $toleranceKm = max(30.0, min(120.0, $totalKm * 0.12));

        if (abs($actualFromStartKm - $expectedAlongKm) > $toleranceKm
            && $crossTrackMeters > $corridorMeters) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $progress
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function syncSkippedCheckpoints(RoutePlan $route, array $progress, array $context): array
    {
        $skipped = $context['skipped_checkpoints'] ?? [];
        $travelledKm = (float) ($progress['distance_travelled_km'] ?? 0);
        $totalKm = (float) ($progress['total_distance_km'] ?? $route->expected_distance_km);
        if ($totalKm <= 0) {
            return $context;
        }

        $positions = $this->progressCalculator->milestonePositionsPct($route);
        $bufferKm = (float) config('routes.checkpoint_skip_buffer_km', 8);

        foreach ($route->checkpoints as $checkpoint) {
            $seq = (int) $checkpoint->sequence;
            if (in_array($seq, $skipped, true)) {
                continue;
            }
            $pct = (float) ($positions['checkpoint-'.$seq] ?? 50);
            if ($travelledKm > (($pct / 100) * $totalKm) + $bufferKm) {
                $skipped[] = $seq;
            }
        }

        $context['skipped_checkpoints'] = array_values(array_unique($skipped));

        return $context;
    }

    private function isWaitingForStart(?TripLog $trip): bool
    {
        return $trip?->isSessionActive()
            && ! empty($trip->navigation_context['route_start_pending'])
            && $trip->started_at === null;
    }

    private function startRadiusMeters(RoutePlan $route): int
    {
        return max(
            100,
            (int) config('routes.trip_start_radius_meters', 500),
            (int) ($route->arrival_radius_meters ?: 0),
        );
    }

    private function hasReachedStartArea(RoutePlan $route, float $lat, float $lng): bool
    {
        $distanceMeters = GeoMath::haversineKm(
            $lat,
            $lng,
            (float) $route->start_lat,
            (float) $route->start_lng,
        ) * 1000;

        return $distanceMeters <= $this->startRadiusMeters($route);
    }

    private function activateTripStart(TripLog $trip, float $lat, float $lng): TripLog
    {
        $context = is_array($trip->navigation_context) ? $trip->navigation_context : [];
        $context['route_start_pending'] = false;
        $context['trip_start_joining'] = false;
        $context['last_state'] = SmartRouteMatcher::STATE_ON_ASSIGNED;
        $context['started_from_start_area_at'] = now()->toIso8601String();

        $trip->started_at = now();
        $trip->start_lat = $lat;
        $trip->start_lng = $lng;
        $trip->last_lat = $lat;
        $trip->last_lng = $lng;
        $trip->navigation_context = $context;
        $trip->checkpoint_timings = array_merge(
            is_array($trip->checkpoint_timings) ? $trip->checkpoint_timings : [],
            ['departed' => $trip->started_at->toIso8601String()],
        );
        $trip->save();

        return $trip;
    }

    private function syncWaitingForStart(
        RoutePlan $route,
        float $lat,
        float $lng,
        ?float $speedKmh,
        ?float $headingDeg,
        TripLog $trip,
    ): TripLog {
        $context = is_array($trip->navigation_context) ? $trip->navigation_context : [];
        $context['route_start_pending'] = true;
        $context['trip_start_joining'] = true;
        $context['start_radius_meters'] = $this->startRadiusMeters($route);

        $progress = $this->progressCalculator->calculate($route, $lat, $lng, $speedKmh, $headingDeg, $context);
        $nextContext = is_array($progress['navigation_context'] ?? null)
            ? $progress['navigation_context']
            : $context;

        $nextContext['route_start_pending'] = true;
        $nextContext['trip_start_joining'] = true;
        $nextContext['last_state'] = SmartRouteMatcher::STATE_JOINING;
        $nextContext['start_radius_meters'] = $this->startRadiusMeters($route);

        $trip->fill([
            'current_checkpoint_sequence' => 0,
            'current_segment_label' => $progress['current_segment_label'] ?? null,
            'distance_travelled_km' => 0,
            'corridor_progress_km' => 0,
            'remaining_distance_km' => (float) ($trip->expected_distance_km ?? $progress['total_distance_km'] ?? 0),
            'eta_at' => null,
            'status' => TripLog::STATUS_IN_PROGRESS,
            'last_lat' => $lat,
            'last_lng' => $lng,
            'navigation_context' => $nextContext,
            'percentage_completed' => 0,
            'actual_distance_km' => 0,
            'extra_distance_km' => 0,
        ]);
        $trip->save();

        return $trip;
    }

    /**
     * @return array<string, mixed>
     */
    private function initialNavigationContext(RoutePlan $route, ?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return [
                'last_state' => SmartRouteMatcher::STATE_JOINING,
                'route_start_pending' => true,
                'trip_start_joining' => true,
                'armed_at' => now()->toIso8601String(),
                'start_radius_meters' => $this->startRadiusMeters($route),
            ];
        }

        $startDistKm = GeoMath::haversineKm(
            $lat,
            $lng,
            (float) $route->start_lat,
            (float) $route->start_lng,
        );
        if (($startDistKm * 1000) > $this->startRadiusMeters($route)) {
            return [
                'last_state' => SmartRouteMatcher::STATE_JOINING,
                'route_start_pending' => true,
                'trip_start_joining' => true,
                'armed_at' => now()->toIso8601String(),
                'start_radius_meters' => $this->startRadiusMeters($route),
                'last_distance_from_start_m' => round($startDistKm * 1000, 1),
            ];
        }

        return ['last_state' => SmartRouteMatcher::STATE_ON_ASSIGNED];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTripSummary(TripLog $trip, RoutePlan $route): array
    {
        $stats = is_array($trip->trip_statistics) ? $trip->trip_statistics : [];
        $expectedMin = (int) $route->expected_duration_minutes;
        $actualMin = $trip->started_at && $trip->completed_at
            ? max(0, (int) $trip->started_at->diffInMinutes($trip->completed_at))
            : null;

        return [
            'assigned_route_id' => $route->id,
            'expected_distance_km' => (float) ($trip->expected_distance_km ?? $route->expected_distance_km),
            'actual_distance_km' => (float) $trip->actual_distance_km,
            'extra_distance_km' => (float) $trip->extra_distance_km,
            'corridor_progress_km' => (float) $trip->corridor_progress_km,
            'percentage_completed' => (float) $trip->percentage_completed,
            'expected_duration_minutes' => $expectedMin,
            'actual_duration_minutes' => $actualMin,
            'delay_minutes' => $actualMin !== null ? max(0, $actualMin - $expectedMin) : null,
            'max_speed_kmh' => (float) ($stats['max_speed_kmh'] ?? 0),
            'avg_speed_kmh' => (float) ($stats['avg_speed_kmh'] ?? 0),
            'moving_time_seconds' => (int) ($stats['moving_time_seconds'] ?? 0),
            'idle_time_seconds' => (int) ($stats['idle_time_seconds'] ?? 0),
            'stopped_time_seconds' => (int) ($stats['stopped_time_seconds'] ?? 0),
            'navigation_recalculations' => (int) ($stats['navigation_recalculations'] ?? 0),
            'final_navigation_state' => $trip->navigation_context['last_state'] ?? null,
            'started_at' => $trip->started_at?->toIso8601String(),
            'completed_at' => $trip->completed_at?->toIso8601String(),
            'checkpoint_timings' => $trip->checkpoint_timings,
            'skipped_checkpoints' => $trip->navigation_context['skipped_checkpoints'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $progress
     * @return array<string, mixed>
     */
    private function buildPayload(
        RoutePlan $route,
        ?TripLog $trip,
        ?array $progress,
        ?float $vehicleLat = null,
        ?float $vehicleLng = null,
        bool $tripModeActive = false,
    ): array {
        if ($progress) {
            $progress = $this->finalizeProgress($progress, $route, $trip);
        }

        $showPolyline = (bool) $route->show_polyline;
        $assignedPolyline = $showPolyline ? $this->guidance->pathVertices($route) : [];
        $actualPolyline = ($showPolyline && $trip?->actual_route_polyline)
            ? PolylineDecoder::decode($trip->actual_route_polyline)
            : [];
        $navigationPolyline = ($showPolyline && $trip?->navigation_route_polyline)
            ? PolylineDecoder::decode($trip->navigation_route_polyline)
            : null;
        $joinPolyline = [];
        $navigationDistanceKm = null;
        $navigationDurationMinutes = null;

        if ($showPolyline && $tripModeActive && $vehicleLat !== null && $vehicleLng !== null && $trip && ! $this->isWaitingForStart($trip)) {
            $navState = is_array($progress)
                ? (string) ($progress['navigation_state'] ?? '')
                : '';
            if ($navState === '') {
                $navState = (string) ($trip->navigation_context['last_state'] ?? '');
            }

            $forceNav = $navigationPolyline === null
                || $navigationPolyline === []
                || ! empty($trip->navigation_context['trip_start_joining'])
                || in_array($navState, [
                    SmartRouteMatcher::STATE_JOINING,
                    SmartRouteMatcher::STATE_OFF_ROUTE,
                    SmartRouteMatcher::STATE_RECALCULATED,
                ], true);

            $nav = $this->tripNavigation->resolve(
                $trip,
                $route,
                $vehicleLat,
                $vehicleLng,
                $forceNav,
            );
            if ($nav && ! empty($nav['vertices'])) {
                $navigationPolyline = $nav['vertices'];
                $navigationDistanceKm = (float) ($nav['distance_km'] ?? 0);
                $navigationDurationMinutes = (int) ($nav['duration_minutes'] ?? 0);
            }

            if ($this->tripNavigation->shouldShowJoinPolyline($route, $trip, $vehicleLat, $vehicleLng, $progress)) {
                $joinPolyline = $this->tripNavigation->joinPolyline($route, $vehicleLat, $vehicleLng);
            }
        }

        $checkpointCount = $route->relationLoaded('checkpoints')
            ? $route->checkpoints->count()
            : $route->checkpoints()->count();

        $stats = is_array($trip?->trip_statistics) ? $trip->trip_statistics : [];
        $expectedKm = (float) ($trip?->expected_distance_km ?? $route->guided_distance_km ?? $route->expected_distance_km);
        $actualKm = (float) ($trip?->actual_distance_km ?? 0);
        $extraKm = (float) ($trip?->extra_distance_km ?? max(0, $actualKm - $expectedKm));
        $waitingForStart = $this->isWaitingForStart($trip);

        $payload = [
            'trip_mode_active' => $tripModeActive,
            'route' => [
                'id' => $route->id,
                'name' => $route->name,
                'start_city' => $route->start_city,
                'destination_city' => $route->destination_city,
                'expected_distance_km' => (float) $route->expected_distance_km,
                'expected_duration_minutes' => (int) $route->expected_duration_minutes,
                'show_polyline' => $showPolyline,
                'has_stored_polyline' => $showPolyline && $route->hasStoredPolyline(),
                'is_road_polyline' => $showPolyline && (
                    $route->hasStoredPolyline()
                    || count($assignedPolyline) > ($checkpointCount + 4)
                ),
                'guided_distance_km' => $route->guided_distance_km ? (float) $route->guided_distance_km : null,
                'show_progress_bar' => (bool) $route->show_progress_bar,
                'arrival_radius_meters' => (int) $route->arrival_radius_meters,
                'assigned_polyline' => $assignedPolyline,
                'polyline' => array_map(
                    fn (array $point) => [
                        'lat' => $point['lat'],
                        'lng' => $point['lng'],
                        'label' => '',
                    ],
                    $assignedPolyline,
                ),
                'admin_polyline' => $showPolyline ? $route->polylineVertices() : [],
                'guided_polyline' => $assignedPolyline,
                'actual_polyline' => $actualPolyline,
                'navigation_polyline' => $navigationPolyline,
                'join_polyline' => $joinPolyline,
                'navigation_distance_km' => $navigationDistanceKm,
                'navigation_duration_minutes' => $navigationDurationMinutes,
                'dynamic_polyline' => $navigationPolyline,
                'display_polyline' => $navigationPolyline ?? $assignedPolyline,
                'checkpoints' => $this->guidance->checkpointMarkers($route),
                'assigned_hint' => (string) __('app.routes.assigned_route_hint'),
            ],
            'trip' => $trip ? [
                'id' => $trip->id,
                'status' => $trip->status,
                'status_label' => $waitingForStart
                    ? __('app.routes.trip_waiting_for_start')
                    : __('app.routes.trip_status_'.$trip->status),
                'started_at' => $trip->started_at?->toIso8601String(),
                'reached_destination_at' => $trip->reached_destination_at?->toIso8601String(),
                'completed_at' => $trip->completed_at?->toIso8601String(),
                'waiting_for_start' => $waitingForStart,
                'start_radius_meters' => $this->startRadiusMeters($route),
                'can_start_trip' => ! $tripModeActive && (! $trip || $trip->isTerminal() || $trip->status === TripLog::STATUS_CANCELLED),
                'can_restart_trip' => $tripModeActive && $trip->isSessionActive(),
                'can_complete' => $tripModeActive && ! $waitingForStart && in_array($trip->status, [
                    TripLog::STATUS_REACHED_DESTINATION,
                    TripLog::STATUS_IN_PROGRESS,
                ], true),
                'can_start_new' => $trip->isTerminal(),
            ] : [
                'status' => null,
                'status_label' => null,
                'can_start_trip' => true,
                'can_restart_trip' => false,
                'can_complete' => false,
                'can_start_new' => false,
            ],
            'progress' => $progress ? array_merge($progress, [
                'expected_distance_km' => $expectedKm,
                'actual_distance_km' => $actualKm,
                'extra_distance_km' => $extraKm,
                'corridor_progress_km' => (float) ($progress['corridor_progress_km'] ?? $trip?->corridor_progress_km ?? 0),
                'expected_duration_minutes' => (int) $route->expected_duration_minutes,
                'actual_duration_minutes' => $trip?->started_at
                    ? max(0, (int) $trip->started_at->diffInMinutes(now()))
                    : null,
                'delay_minutes' => $trip?->started_at
                    ? max(0, (int) $trip->started_at->diffInMinutes(now()) - (int) $route->expected_duration_minutes)
                    : null,
                'max_speed_kmh' => (float) ($stats['max_speed_kmh'] ?? 0),
                'avg_speed_kmh' => (float) ($stats['avg_speed_kmh'] ?? 0),
                'moving_time_seconds' => (int) ($stats['moving_time_seconds'] ?? 0),
                'idle_time_seconds' => (int) ($stats['idle_time_seconds'] ?? 0),
                'stopped_time_seconds' => (int) ($stats['stopped_time_seconds'] ?? 0),
                'gps_accuracy_m' => $stats['gps_accuracy_m'] ?? null,
                'navigation_recalculations' => (int) ($stats['navigation_recalculations'] ?? 0),
                'segment_breakdown' => $this->segmentBreakdown($route, $trip, $progress),
                'milestones' => $this->milestoneTimeline($route, $trip, $progress),
                'next_checkpoint_label' => null,
                'waiting_for_start' => $waitingForStart,
                'start_radius_meters' => $this->startRadiusMeters($route),
            ]) : null,
        ];

        if ($progress && isset($payload['progress']['milestones'])) {
            $payload['progress']['next_checkpoint_label'] = $this->nextCheckpointLabel($payload['progress']['milestones']);
            $payload['progress']['current_city'] = $this->currentCityLabel($route, $payload['progress']);
            $payload['progress']['next_city'] = $this->nextCityLabel($payload['progress']['milestones']);
        }

        if ($tripModeActive && $trip?->eta_at && isset($payload['progress'])) {
            $payload['progress']['eta_human'] = app_datetime_format($trip->eta_at);
            $payload['progress']['navigation_eta_human'] = app_datetime_format($trip->eta_at);
            if ($navigationDistanceKm !== null && $navigationDistanceKm > 0) {
                $payload['progress']['navigation_remaining_km'] = round($navigationDistanceKm, 1);
            }
            $payload['progress']['suggested_route_label'] = (string) __('app.routes.suggested_route_to_destination');
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $milestones
     */
    private function nextCheckpointLabel(array $milestones): ?string
    {
        foreach ($milestones as $milestone) {
            if (($milestone['kind'] ?? '') === 'start') {
                continue;
            }
            if (in_array($milestone['status'] ?? '', ['skipped'], true)) {
                continue;
            }
            if (($milestone['status'] ?? '') !== 'reached') {
                return (string) ($milestone['label'] ?? '');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function currentCityLabel(RoutePlan $route, array $progress): string
    {
        $milestones = $progress['milestones'] ?? [];
        $countsProgress = (bool) ($progress['progress_counting'] ?? true);

        foreach (array_reverse($milestones) as $milestone) {
            if (($milestone['status'] ?? '') === 'reached') {
                return (string) ($milestone['label'] ?? '');
            }
            if ($countsProgress && ($milestone['status'] ?? '') === 'current') {
                return (string) ($milestone['label'] ?? '');
            }
        }

        return (string) ($route->start_city ?? '');
    }

    /**
     * @param  list<array<string, mixed>>  $milestones
     */
    private function nextCityLabel(array $milestones): ?string
    {
        foreach ($milestones as $milestone) {
            if (($milestone['kind'] ?? '') === 'start') {
                continue;
            }
            if (($milestone['kind'] ?? '') === 'destination' && ($milestone['status'] ?? '') === 'reached') {
                return null;
            }
            if (in_array($milestone['status'] ?? '', ['skipped'], true)) {
                continue;
            }
            if (($milestone['status'] ?? '') !== 'reached') {
                return (string) ($milestone['label'] ?? '');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function syncCheckpointTimings(TripLog $trip, array $progress, int $previousSequence): void
    {
        $timings = is_array($trip->checkpoint_timings) ? $trip->checkpoint_timings : [];
        $now = now();
        $newSequence = (int) ($progress['current_checkpoint_sequence'] ?? 0);

        if ($trip->started_at && ! isset($timings['departed'])) {
            $timings['departed'] = $trip->started_at->toIso8601String();
        }

        if ($newSequence > $previousSequence && $previousSequence > 0) {
            $key = (string) $previousSequence;
            if (! isset($timings[$key]['reached_at'])) {
                $prevKey = (string) ($previousSequence - 1);
                $fromIso = $timings[$prevKey]['reached_at'] ?? $timings['departed'] ?? $trip->started_at?->toIso8601String();
                $from = $fromIso ? \Carbon\Carbon::parse($fromIso) : $now;
                $timings[$key] = [
                    'reached_at' => $now->toIso8601String(),
                    'duration_seconds' => max(0, (int) $from->diffInSeconds($now)),
                ];
            }
        }

        $trip->checkpoint_timings = $timings;
    }

    /**
     * @param  array<string, mixed>  $progress
     */
    private function recordFinalSegmentTiming(TripLog $trip, RoutePlan $route, array $progress): void
    {
        $route->loadMissing('checkpoints');
        $finalSeq = $route->checkpoints->count() + 1;
        $timings = is_array($trip->checkpoint_timings) ? $trip->checkpoint_timings : [];
        $key = (string) $finalSeq;

        if (isset($timings[$key]['reached_at'])) {
            return;
        }

        $prevKey = (string) max(1, (int) ($progress['current_checkpoint_sequence'] ?? $finalSeq - 1));
        $fromIso = $timings[$prevKey]['reached_at'] ?? $timings['departed'] ?? $trip->started_at?->toIso8601String();
        $from = $fromIso ? \Carbon\Carbon::parse($fromIso) : now();
        $now = now();

        $timings[$key] = [
            'reached_at' => $now->toIso8601String(),
            'duration_seconds' => max(0, (int) $from->diffInSeconds($now)),
        ];
        $trip->checkpoint_timings = $timings;
    }

    /**
     * @param  array<string, mixed>|null  $progress
     * @return array<string, mixed>
     */
    private function segmentBreakdown(RoutePlan $route, ?TripLog $trip, ?array $progress): array
    {
        $route->loadMissing('checkpoints');
        $timings = is_array($trip?->checkpoint_timings) ? $trip->checkpoint_timings : [];
        $currentSeq = (int) ($progress['current_checkpoint_sequence'] ?? $trip?->current_checkpoint_sequence ?? 0);
        $segments = [];
        $checkpointMinutesTotal = 0;

        foreach ($route->checkpoints as $checkpoint) {
            $seq = (int) $checkpoint->sequence;
            $timing = $timings[(string) $seq] ?? null;
            $actualMin = isset($timing['duration_seconds'])
                ? (int) round(((int) $timing['duration_seconds']) / 60)
                : null;

            if ($actualMin !== null) {
                $checkpointMinutesTotal += $actualMin;
            }

            $status = 'pending';
            if ($currentSeq > 0 && $seq < $currentSeq) {
                $status = 'completed';
            } elseif ($seq === $currentSeq) {
                $status = 'current';
            }

            $segments[] = [
                'sequence' => $seq,
                'label' => $checkpoint->segmentLabel(),
                'expected_minutes' => (int) $checkpoint->expected_duration_minutes,
                'actual_minutes' => $actualMin,
                'status' => $status,
            ];
        }

        $lastCheckpoint = $route->checkpoints->last();
        $finalLabel = $lastCheckpoint
            ? $lastCheckpoint->to_location.' → '.$route->destination_city
            : $route->start_city.' → '.$route->destination_city;
        $finalSeq = count($segments) + 1;
        $finalTiming = $timings[(string) $finalSeq] ?? null;
        $finalActual = isset($finalTiming['duration_seconds'])
            ? (int) round(((int) $finalTiming['duration_seconds']) / 60)
            : null;

        if ($finalActual !== null) {
            $checkpointMinutesTotal += $finalActual;
        }

        $finalStatus = 'pending';
        if ($trip?->reached_destination_at || $trip?->isTerminal()) {
            $finalStatus = 'completed';
        } elseif ($currentSeq >= $finalSeq) {
            $finalStatus = 'current';
        }

        $segments[] = [
            'sequence' => $finalSeq,
            'label' => $finalLabel,
            'expected_minutes' => 0,
            'actual_minutes' => $finalActual,
            'status' => $finalStatus,
        ];

        $elapsedMinutes = null;
        if ($trip?->started_at) {
            $end = $trip->completed_at ?? now();
            $elapsedMinutes = (int) $trip->started_at->diffInMinutes($end);
        }

        return [
            'segments' => $segments,
            'elapsed_minutes' => $elapsedMinutes,
            'expected_minutes' => (int) $route->expected_duration_minutes,
            'checkpoint_minutes_total' => $checkpointMinutesTotal > 0 ? $checkpointMinutesTotal : null,
        ];
    }

    /**
     * Ordered start → checkpoints → destination for the progress bar.
     *
     * @param  array<string, mixed>|null  $progress
     * @return list<array<string, mixed>>
     */
    private function milestoneTimeline(RoutePlan $route, ?TripLog $trip, ?array $progress): array
    {
        $route->loadMissing('checkpoints');
        $timings = is_array($trip?->checkpoint_timings) ? $trip->checkpoint_timings : [];
        $currentSeq = (int) ($progress['current_checkpoint_sequence'] ?? $trip?->current_checkpoint_sequence ?? 0);
        $positions = $this->progressCalculator->milestonePositionsPct($route);
        $finalSeq = $route->checkpoints->count() + 1;
        $waitingForStart = $this->isWaitingForStart($trip);
        $tripStarted = ! $waitingForStart && ($trip?->started_at !== null
            || isset($timings['departed'])
            || in_array($trip?->status, [
                TripLog::STATUS_IN_PROGRESS,
                TripLog::STATUS_REACHED_DESTINATION,
                TripLog::STATUS_COMPLETED,
            ], true)
            || $currentSeq > 0);

        $destReached = ($progress['progress_counting'] ?? false)
            && (
                $trip?->reached_destination_at !== null
                || $trip?->status === TripLog::STATUS_REACHED_DESTINATION
                || $trip?->status === TripLog::STATUS_COMPLETED
                || ($progress['at_destination'] ?? false)
            );

        $countsProgress = (bool) ($progress['progress_counting'] ?? false);

        $locality = app(GeoLocalityResolver::class);

        $milestones = [[
            'id' => 'start',
            'kind' => 'start',
            'label' => $locality->sanitizeLabel($route->start_city) ?? $route->start_city,
            'sequence' => 0,
            'position_pct' => $positions['start'] ?? 0.0,
            'status' => $tripStarted ? 'reached' : 'current',
        ]];

        foreach ($route->checkpoints as $checkpoint) {
            $seq = (int) $checkpoint->sequence;
            $skipped = in_array($seq, $navSkipped = ($trip ? (is_array($trip->navigation_context) ? ($trip->navigation_context['skipped_checkpoints'] ?? []) : []) : []), true);
            $reached = ! $skipped && ($currentSeq > $seq
                || isset($timings[(string) $seq]['reached_at']));
            $status = 'pending';
            if ($skipped) {
                $status = 'skipped';
            } elseif ($reached) {
                $status = 'reached';
            } elseif ($seq === $currentSeq && ! $destReached && $countsProgress) {
                $status = 'current';
            }

            $milestones[] = [
                'id' => 'checkpoint-'.$seq,
                'kind' => 'checkpoint',
                'label' => $locality->sanitizeLabel($checkpoint->to_location) ?? $checkpoint->to_location,
                'sequence' => $seq,
                'position_pct' => $positions['checkpoint-'.$seq] ?? 50.0,
                'status' => $status,
            ];
        }

        $destStatus = 'pending';
        if ($destReached) {
            $destStatus = 'reached';
        } elseif ($tripStarted && $currentSeq >= $finalSeq && $countsProgress && ! $destReached) {
            $destStatus = 'current';
        }

        $milestones[] = [
            'id' => 'destination',
            'kind' => 'destination',
            'label' => $locality->sanitizeLabel($route->destination_city) ?? $route->destination_city,
            'sequence' => $finalSeq,
            'position_pct' => $positions['destination'] ?? 100.0,
            'status' => $destStatus,
        ];

        return $milestones;
    }

    /**
     * @return array<string, mixed>
     */
    private function tripProgressSnapshot(TripLog $trip, RoutePlan $route): array
    {
        $context = is_array($trip->navigation_context) ? $trip->navigation_context : [];

        return $this->finalizeProgress([
            'current_checkpoint_sequence' => $trip->current_checkpoint_sequence,
            'current_segment_label' => $trip->current_segment_label,
            'percentage_completed' => (float) $trip->percentage_completed,
            'distance_travelled_km' => (float) $trip->distance_travelled_km,
            'remaining_distance_km' => (float) $trip->remaining_distance_km,
            'total_distance_km' => (float) ($route->guided_distance_km ?: $route->expected_distance_km),
            'expected_duration_minutes' => (int) $route->expected_duration_minutes,
            'eta_at' => $trip->eta_at?->toIso8601String(),
            'eta_human' => $trip->eta_at ? app_datetime_format($trip->eta_at) : null,
            'estimated_arrival_time' => $trip->eta_at ? app_datetime_format($trip->eta_at) : null,
            'progress_bar_position' => min(1, max(0, ((float) $trip->percentage_completed) / 100)),
            'navigation_state' => $context['last_state'] ?? SmartRouteMatcher::STATE_ON_ASSIGNED,
            'navigation_state_label' => (string) __('app.routes.navigation_state_'.($context['last_state'] ?? SmartRouteMatcher::STATE_ON_ASSIGNED)),
            'off_route_alert' => ($context['last_state'] ?? '') === SmartRouteMatcher::STATE_OFF_ROUTE,
            'cross_track_km' => $context['last_cross_track_km'] ?? null,
            'distance_from_start_m' => $context['last_distance_from_start_m'] ?? null,
            'position_is_live' => false,
            'progress_stale_label' => (string) __('app.routes.progress_stale_position'),
        ], $route, $trip);
    }

    /**
     * @param  array<string, mixed>  $progress
     * @return array<string, mixed>
     */
    private function reconcileProgressNumbers(array $progress, RoutePlan $route): array
    {
        $total = (float) ($progress['total_distance_km'] ?? 0);
        $planned = (float) ($route->guided_distance_km ?: $route->expected_distance_km);
        if ($planned > 0 && ($total <= 0 || abs($total - $planned) / max($planned, 1) > 0.15)) {
            $total = $planned;
        }

        $traveled = min(max(0, (float) ($progress['distance_travelled_km'] ?? 0)), max($total, 0));
        $remaining = $total > 0 ? max(0, $total - $traveled) : max(0, (float) ($progress['remaining_distance_km'] ?? 0));
        $pct = $total > 0 ? min(100, round(($traveled / $total) * 100, 2)) : (float) ($progress['percentage_completed'] ?? 0);

        if ($progress['at_destination'] ?? false) {
            $pct = 100;
            $traveled = $total;
            $remaining = 0;
        }

        $progress['total_distance_km'] = round($total, 3);
        $progress['distance_travelled_km'] = round($traveled, 3);
        $progress['remaining_distance_km'] = round($remaining, 3);
        $progress['percentage_completed'] = $pct;
        $progress['progress_bar_position'] = round(min(1, max(0, $pct / 100)), 4);

        return $progress;
    }

    private function notifyTripCompleted(Device $device, RoutePlan $route, TripLog $trip): void
    {
        try {
            $startedAt = $trip->started_at ?? $trip->created_at;
            $completedAt = $trip->completed_at ?? now();
            $travelMinutes = $startedAt ? $startedAt->diffInMinutes($completedAt) : null;

            $this->pushDispatcher->dispatchRouteTripCompleted(
                device: $device,
                route: $route,
                trip: $trip,
                travelMinutes: $travelMinutes,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
