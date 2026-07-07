<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\DeviceLocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived file cache so parallel history endpoints share one DB fetch.
 */
class HistoryTrackCache
{
    private const TTL_SECONDS = 90;

    public function key(Device $device, Carbon $from, ?Carbon $to): string
    {
        return sprintf('hist_track:%d:%d:%s', $device->id, $from->timestamp, $to?->timestamp ?? 'open');
    }

    /**
     * @return array{locations: Collection<int, DeviceLocation>, used_fallback: bool, fallback_reason: ?string}|null
     */
    public function get(Device $device, Carbon $from, ?Carbon $to): ?array
    {
        $payload = Cache::store('file')->get($this->key($device, $from, $to));
        if (! is_array($payload) || ! isset($payload['slim']) || ! is_array($payload['slim'])) {
            return null;
        }

        return [
            'locations' => $this->hydrate($payload['slim']),
            'used_fallback' => (bool) ($payload['used_fallback'] ?? false),
            'fallback_reason' => $payload['fallback_reason'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     */
    public function put(
        Device $device,
        Carbon $from,
        ?Carbon $to,
        Collection $locations,
        bool $usedFallback,
        ?string $fallbackReason,
    ): void {
        if ($locations->isEmpty()) {
            return;
        }

        Cache::store('file')->put($this->key($device, $from, $to), [
            'slim' => $this->dehydrate($locations),
            'used_fallback' => $usedFallback,
            'fallback_reason' => $fallbackReason,
            'count' => $locations->count(),
        ], self::TTL_SECONDS);
    }

    /**
     * @return array{locations: Collection<int, DeviceLocation>, used_fallback: bool, fallback_reason: ?string}
     */
    public function remember(Device $device, Carbon $from, ?Carbon $to, callable $resolver): array
    {
        $cached = $this->get($device, $from, $to);
        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock($this->key($device, $from, $to) . ':lock', 120);

        try {
            return $lock->block(120, function () use ($device, $from, $to, $resolver) {
                $cached = $this->get($device, $from, $to);
                if ($cached !== null) {
                    return $cached;
                }

                /** @var array{locations: Collection<int, DeviceLocation>, used_fallback: bool, fallback_reason: ?string} $result */
                $result = $resolver();
                $this->put(
                    $device,
                    $from,
                    $to,
                    $result['locations'],
                    (bool) ($result['used_fallback'] ?? false),
                    $result['fallback_reason'] ?? null,
                );

                return $result;
            });
        } catch (\Throwable) {
            /** @var array{locations: Collection<int, DeviceLocation>, used_fallback: bool, fallback_reason: ?string} */
            return $resolver();
        }
    }

    /**
     * @param  Collection<int, DeviceLocation>  $locations
     * @return list<array<string, mixed>>
     */
    private function dehydrate(Collection $locations): array
    {
        $out = [];

        foreach ($locations as $loc) {
            $out[] = [
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
                'speed' => (float) ($loc->speed ?? 0),
                'heading' => (float) ($loc->heading ?? 0),
                'ignition' => (bool) $loc->ignition,
                'acc' => (bool) ($loc->acc ?? false),
                'altitude' => $loc->altitude !== null ? (float) $loc->altitude : null,
                'recorded_at' => $loc->recorded_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $slim
     * @return Collection<int, DeviceLocation>
     */
    private function hydrate(array $slim): Collection
    {
        return collect($slim)->map(function (array $row) {
            $loc = new DeviceLocation;
            $loc->lat = $row['lat'];
            $loc->lng = $row['lng'];
            $loc->speed = $row['speed'] ?? 0;
            $loc->heading = $row['heading'] ?? 0;
            $loc->ignition = $row['ignition'] ?? false;
            $loc->acc = $row['acc'] ?? false;
            $loc->altitude = $row['altitude'] ?? null;
            if (! empty($row['recorded_at'])) {
                $loc->recorded_at = Carbon::parse($row['recorded_at']);
            }

            return $loc;
        });
    }
}
