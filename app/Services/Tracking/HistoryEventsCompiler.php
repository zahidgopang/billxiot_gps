<?php

namespace App\Services\Tracking;

use App\Models\VehicleEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Merge Traccar/Laravel alerts with timeline-derived status events for history UI.
 */
class HistoryEventsCompiler
{
    private const MIN_SEGMENT_SECONDS = 45;

    /**
     * @param  Collection<int, VehicleEvent>  $dbEvents
     * @param  list<array<string, mixed>>  $timeline
     * @param  list<array<string, mixed>>  $stops
     * @return list<array<string, mixed>>
     */
    public function compile(Collection $dbEvents, array $timeline, array $stops = []): array
    {
        $merged = [];

        foreach ($dbEvents as $event) {
            $row = $event->toAlertArray();
            $row['source'] = 'alert';
            $row['sort_at'] = $this->sortKey($row['time'] ?? $row['recorded_at'] ?? null);
            $merged[] = $row;
        }

        foreach ($timeline as $segment) {
            $compiled = $this->fromTimelineSegment($segment);
            if ($compiled !== null) {
                $merged[] = $compiled;
            }
        }

        foreach ($stops as $stop) {
            $compiled = $this->fromStop($stop);
            if ($compiled !== null) {
                $merged[] = $compiled;
            }
        }

        usort($merged, fn (array $a, array $b) => ($b['sort_at'] ?? 0) <=> ($a['sort_at'] ?? 0));

        $seen = [];
        $out = [];
        foreach ($merged as $row) {
            $key = implode('|', [
                $row['event_type'] ?? '',
                $row['sort_at'] ?? '',
                round((float) ($row['lat'] ?? 0), 4),
                round((float) ($row['lng'] ?? 0), 4),
                substr((string) ($row['title'] ?? ''), 0, 40),
            ]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            unset($row['sort_at'], $row['source']);
            $out[] = $row;
        }

        return array_slice($out, 0, 400);
    }

    /**
     * @param  array<string, mixed>  $segment
     * @return array<string, mixed>|null
     */
    private function fromTimelineSegment(array $segment): ?array
    {
        $isTransition = (bool) ($segment['is_transition'] ?? false);
        $duration = (int) ($segment['duration_seconds'] ?? 0);
        $key = (string) ($segment['status_key'] ?? '');

        if ($isTransition) {
            $time = $segment['start'] ?? null;
        } elseif ($duration < self::MIN_SEGMENT_SECONDS) {
            return null;
        } elseif (! in_array($key, ['moving', 'running', 'parked', 'stopped', 'idle', 'offline', 'ignition_on', 'ignition_off'], true)) {
            return null;
        } else {
            $time = $segment['start'] ?? null;
        }

        $lat = $segment['start_lat'] ?? $segment['end_lat'] ?? null;
        $lng = $segment['start_lng'] ?? $segment['end_lng'] ?? null;

        if ($lat === null || $lng === null || abs((float) $lat) < 1e-5 && abs((float) $lng) < 1e-5) {
            return null;
        }

        $label = (string) ($segment['status_label'] ?? ucfirst(str_replace('_', ' ', $key)));
        $message = $isTransition
            ? $label
            : sprintf('%s · %s', $label, $this->formatDuration($duration));

        return [
            'id' => null,
            'event_type' => $key,
            'type' => 'info',
            'title' => $label,
            'message' => $message,
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'time' => $time,
            'recorded_at' => $time,
            'duration_seconds' => $duration,
            'source' => 'timeline',
            'sort_at' => $this->sortKey($time),
        ];
    }

    /**
     * @param  array<string, mixed>  $stop
     * @return array<string, mixed>|null
     */
    private function fromStop(array $stop): ?array
    {
        $lat = $stop['lat'] ?? null;
        $lng = $stop['lng'] ?? null;
        if ($lat === null || $lng === null) {
            return null;
        }

        $motion = (string) ($stop['motion_key'] ?? 'parked');
        $label = (string) ($stop['status_label'] ?? ucfirst($motion));
        $duration = (int) ($stop['duration_seconds'] ?? 0);
        $time = $stop['start'] ?? $stop['start_display'] ?? null;

        return [
            'id' => null,
            'event_type' => $motion,
            'type' => 'info',
            'title' => $label,
            'message' => sprintf('%s · %s', $label, $this->formatDuration($duration)),
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'time' => $time,
            'recorded_at' => $time,
            'duration_seconds' => $duration,
            'source' => 'stop',
            'sort_at' => $this->sortKey($time),
        ];
    }

    private function sortKey(mixed $time): int
    {
        if ($time === null || $time === '') {
            return 0;
        }

        try {
            return Carbon::parse($time)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        $parts = [];
        if ($h > 0) {
            $parts[] = "{$h} h";
        }
        if ($h > 0 || $m > 0) {
            $parts[] = "{$m} min";
        }
        $parts[] = "{$s} s";

        return implode(' ', $parts);
    }
}
