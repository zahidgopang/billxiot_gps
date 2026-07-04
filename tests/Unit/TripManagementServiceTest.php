<?php

namespace Tests\Unit;

use App\Models\RoutePlan;
use App\Models\TripLog;
use App\Services\Routes\ActualRouteRecorder;
use App\Services\Routes\RouteGuidanceService;
use App\Services\Routes\RouteProgressCalculator;
use App\Services\Routes\SmartRouteMatcher;
use App\Services\Routes\TripManagementService;
use App\Services\Routes\TripNavigationService;
use App\Services\Push\PushNotificationDispatcher;
use ReflectionMethod;
use Tests\TestCase;

class TripManagementServiceTest extends TestCase
{
    public function test_completed_trip_is_terminal(): void
    {
        $trip = new TripLog(['status' => TripLog::STATUS_COMPLETED]);

        $this->assertTrue($trip->isTerminal());
    }

    public function test_in_progress_trip_is_not_terminal(): void
    {
        $trip = new TripLog(['status' => TripLog::STATUS_IN_PROGRESS]);

        $this->assertFalse($trip->isTerminal());
    }

    public function test_stale_progress_not_frozen_when_vehicle_far_from_route(): void
    {
        $route = new RoutePlan([
            'expected_distance_km' => 850,
            'arrival_radius_meters' => 500,
        ]);

        $trip = new TripLog([
            'status' => TripLog::STATUS_IN_PROGRESS,
            'percentage_completed' => 8,
            'distance_travelled_km' => 68,
            'started_at' => now(),
        ]);

        $progress = [
            'cross_track_km' => 180.0,
            'corridor_radius_meters' => 2500,
            'total_distance_km' => 850,
            'distance_from_start_m' => 820000,
            'on_route' => false,
        ];

        $service = $this->makeTripManagementService();
        $method = new ReflectionMethod(TripManagementService::class, 'canFreezeStoredProgressOffRoute');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, $progress, $trip, $route));
    }

    public function test_progress_can_freeze_when_vehicle_near_expected_position(): void
    {
        $route = new RoutePlan([
            'expected_distance_km' => 850,
            'arrival_radius_meters' => 500,
        ]);

        $trip = new TripLog([
            'status' => TripLog::STATUS_IN_PROGRESS,
            'percentage_completed' => 50,
            'distance_travelled_km' => 425,
            'started_at' => now(),
        ]);

        $progress = [
            'cross_track_km' => 4.0,
            'corridor_radius_meters' => 2500,
            'total_distance_km' => 850,
            'distance_from_start_m' => 430000,
            'on_route' => false,
        ];

        $service = $this->makeTripManagementService();
        $method = new ReflectionMethod(TripManagementService::class, 'canFreezeStoredProgressOffRoute');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, $progress, $trip, $route));
    }

    public function test_milestone_timeline_marks_start_and_destination(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'expected_distance_km' => 400,
        ]);
        $route->setRelation('checkpoints', collect());

        $trip = new TripLog([
            'status' => TripLog::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'percentage_completed' => 12,
        ]);

        $service = $this->makeTripManagementService();
        $method = new ReflectionMethod(TripManagementService::class, 'milestoneTimeline');
        $method->setAccessible(true);

        $milestones = $method->invoke($service, $route, $trip, [
            'current_checkpoint_sequence' => 0,
        ]);

        $this->assertCount(2, $milestones);
        $this->assertSame('start', $milestones[0]['id']);
        $this->assertSame('reached', $milestones[0]['status']);
        $this->assertSame('destination', $milestones[1]['id']);
        $this->assertSame('pending', $milestones[1]['status']);
    }

    public function test_offline_off_route_snapshot_shows_zero_percent(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'expected_distance_km' => 462,
            'guided_distance_km' => 462,
        ]);
        $route->setRelation('checkpoints', collect());

        $trip = new TripLog([
            'status' => TripLog::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'percentage_completed' => 73,
            'distance_travelled_km' => 339.3,
            'remaining_distance_km' => 122.8,
            'navigation_context' => [
                'last_state' => 'off_route',
            ],
        ]);

        $service = $this->makeTripManagementService();
        $method = new ReflectionMethod(TripManagementService::class, 'tripProgressSnapshot');
        $method->setAccessible(true);

        $progress = $method->invoke($service, $trip, $route);

        $this->assertSame(0.0, (float) $progress['percentage_completed']);
        $this->assertSame(0.0, (float) $progress['distance_travelled_km']);
        $this->assertFalse($progress['progress_counting']);
        $this->assertTrue($progress['progress_unreliable']);
    }

    public function test_off_route_milestone_does_not_mark_destination_current(): void
    {
        $route = new RoutePlan([
            'start_city' => 'Makkah',
            'destination_city' => 'Madinah',
            'expected_distance_km' => 462,
        ]);
        $route->setRelation('checkpoints', collect());

        $trip = new TripLog([
            'status' => TripLog::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'current_checkpoint_sequence' => 1,
        ]);

        $service = $this->makeTripManagementService();
        $method = new ReflectionMethod(TripManagementService::class, 'milestoneTimeline');
        $method->setAccessible(true);

        $milestones = $method->invoke($service, $route, $trip, [
            'current_checkpoint_sequence' => 1,
            'progress_counting' => false,
            'navigation_state' => 'off_route',
        ]);

        $destination = collect($milestones)->firstWhere('id', 'destination');
        $this->assertSame('pending', $destination['status']);
    }

    public function test_trip_start_context_waits_until_vehicle_enters_start_area(): void
    {
        config(['routes.trip_start_radius_meters' => 500]);

        $route = new RoutePlan([
            'start_lat' => 21.4241,
            'start_lng' => 39.8173,
            'arrival_radius_meters' => 500,
        ]);

        $service = $this->makeTripManagementService();
        $method = new ReflectionMethod(TripManagementService::class, 'initialNavigationContext');
        $method->setAccessible(true);

        $outside = $method->invoke($service, $route, 21.4350, 39.8173);
        $inside = $method->invoke($service, $route, 21.4243, 39.8174);

        $this->assertTrue($outside['route_start_pending']);
        $this->assertTrue($outside['trip_start_joining']);
        $this->assertArrayNotHasKey('route_start_pending', $inside);
        $this->assertSame(SmartRouteMatcher::STATE_ON_ASSIGNED, $inside['last_state']);
    }

    public function test_waiting_for_start_progress_stays_at_zero(): void
    {
        $route = new RoutePlan([
            'expected_distance_km' => 120,
            'guided_distance_km' => 120,
            'arrival_radius_meters' => 500,
        ]);

        $trip = new TripLog([
            'status' => TripLog::STATUS_IN_PROGRESS,
            'started_at' => null,
            'navigation_context' => [
                'route_start_pending' => true,
                'trip_start_joining' => true,
            ],
        ]);

        $service = $this->makeTripManagementService();
        $method = new ReflectionMethod(TripManagementService::class, 'applyNavigationDisplayRules');
        $method->setAccessible(true);

        $progress = $method->invoke($service, [
            'navigation_state' => SmartRouteMatcher::STATE_RECALCULATED,
            'percentage_completed' => 42,
            'distance_travelled_km' => 50,
            'remaining_distance_km' => 70,
            'total_distance_km' => 120,
        ], $route, $trip, $trip->navigation_context);

        $this->assertTrue($progress['waiting_for_start']);
        $this->assertFalse($progress['progress_counting']);
        $this->assertSame(0, $progress['percentage_completed']);
        $this->assertSame(0, $progress['distance_travelled_km']);
        $this->assertSame(0, $progress['progress_bar_position']);
        $this->assertSame(120.0, (float) $progress['remaining_distance_km']);
    }

    private function makeTripManagementService(): TripManagementService
    {
        $guidance = $this->createMock(RouteGuidanceService::class);
        $matcher = new SmartRouteMatcher();

        return new TripManagementService(
            new RouteProgressCalculator($guidance, $matcher),
            $guidance,
            $this->createMock(\App\Services\Push\PushNotificationDispatcher::class),
            new ActualRouteRecorder(),
            new TripNavigationService($guidance, $matcher),
            $matcher,
        );
    }
}
