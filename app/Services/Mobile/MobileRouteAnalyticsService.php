<?php

namespace App\Services\Mobile;

use App\Services\Tracking\HistoryAnalyticsService;
use Illuminate\Support\Collection;

/**
 * @deprecated Prefer HistoryAnalyticsService directly. Kept for backward-compatible API wiring.
 */
class MobileRouteAnalyticsService
{
    public const STOP_SPEED_KMH = 2;

    public const IDLE_SPEED_KMH = 0.5;

    public const STOP_MIN_SECONDS = HistoryAnalyticsService::STOP_MIN_SECONDS;

    public const OVERSPEED_KMH = HistoryAnalyticsService::OVERSPEED_KMH;

    public function __construct(
        private HistoryAnalyticsService $historyAnalytics,
    ) {}

    /**
     * @param  Collection<int, object>  $points
     * @return array<string, mixed>
     */
    public function analyze(Collection $points): array
    {
        return $this->historyAnalytics->analyze($points);
    }

    /**
     * @param  Collection<int, object>  $points
     * @return list<array<string, mixed>>
     */
    public function pointStatuses(Collection $points): array
    {
        return $this->historyAnalytics->pointStatuses($points);
    }
}
