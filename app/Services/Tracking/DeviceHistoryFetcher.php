<?php

namespace App\Services\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Support\Tracking\HistoryRangeBounds;
use Illuminate\Support\Collection;

class DeviceHistoryFetcher
{
    public function __construct(
        private PositionReaderInterface $positions,
    ) {}

    /**
     * Load GPS history; when empty, fall back to the most recent available data.
     *
     * @return array{locations: Collection, used_fallback: bool, fallback_reason: ?string}
     */
    public function fetch(Device $device, \Carbon\Carbon $from, ?\Carbon\Carbon $to, bool $explicitRange): array
    {
        if ($to !== null) {
            $normalized = HistoryRangeBounds::normalize($from, $to);
            $from = $normalized['from'];
            $to = $normalized['to'];
        }

        $locations = $this->positions->historyForDevice($device, $from, $to, 'asc');

        $debug = request()->boolean('debug_gps') || request()->query('debug_gps') === '1';
        if ($explicitRange && $to !== null) {
            HistoryRangeBounds::debugLog(
                $device->id,
                (string) request()->query('from', ''),
                (string) request()->query('to', ''),
                $from,
                $to,
                $locations->count(),
                $debug
            );
        }

        if ($locations->isNotEmpty()) {
            return [
                'locations' => $locations,
                'used_fallback' => false,
                'fallback_reason' => null,
            ];
        }

        if ($explicitRange) {
            $fallback = $this->fallbackWhenEmpty($device);

            if ($fallback !== null) {
                return [
                    'locations' => $fallback['locations'],
                    'used_fallback' => true,
                    'fallback_reason' => 'selected_period_empty',
                ];
            }

            return [
                'locations' => $locations,
                'used_fallback' => false,
                'fallback_reason' => 'selected_period_empty',
            ];
        }

        $fallback = $this->fallbackWhenEmpty($device);

        if ($fallback === null) {
            return [
                'locations' => $locations,
                'used_fallback' => false,
                'fallback_reason' => null,
            ];
        }

        return $fallback;
    }

    /**
     * @return array{locations: Collection, used_fallback: bool, fallback_reason: string}|null
     */
    private function fallbackWhenEmpty(Device $device): ?array
    {
        $latest = $this->positions->latestForDevice($device);

        if ($latest?->recorded_at) {
            $end = $latest->recorded_at->copy();
            $from = $end->copy()->subHours(24);

            $locations = $this->positions->historyForDevice($device, $from, $end, 'asc');

            if ($locations->isNotEmpty()) {
                return $this->result($locations, 'last_known_activity');
            }

            $from = $end->copy()->startOfDay();
            $to = $end->copy()->endOfDay();
            $locations = $this->positions->historyForDevice($device, $from, $to, 'asc');

            if ($locations->isNotEmpty()) {
                return $this->result($locations, 'last_activity_day');
            }
        }

        $locations = $this->positions->historyForDevice($device, now()->subDays(30), null, 'asc');

        if ($locations->isEmpty()) {
            return null;
        }

        return $this->result($locations, '30_days');
    }

    /**
     * @return array{locations: Collection, used_fallback: bool, fallback_reason: string}
     */
    private function result(Collection $locations, string $reason): array
    {
        return [
            'locations' => $locations,
            'used_fallback' => true,
            'fallback_reason' => $reason,
        ];
    }
}
