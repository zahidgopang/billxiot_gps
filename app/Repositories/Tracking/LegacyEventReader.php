<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\EventReaderInterface;
use App\Models\Device;
use App\Models\VehicleEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LegacyEventReader implements EventReaderInterface
{
    public function latestForDevice(Device $device, int $limit = 5): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        return VehicleEvent::query()
            ->where('device_id', $device->id)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    public function afterIdForDevice(Device $device, int $afterId, int $limit = 20): Collection
    {
        if (! $this->tableExists() || $afterId < 1) {
            return collect();
        }

        return VehicleEvent::query()
            ->where('device_id', $device->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function forDevice(
        Device $device,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?array $types = null,
        int $limit = 50
    ): Collection {
        if (! $this->tableExists()) {
            return collect();
        }

        $query = VehicleEvent::query()
            ->where('device_id', $device->id)
            ->orderByDesc('occurred_at')
            ->limit($limit);

        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to) {
            $query->where('occurred_at', '<=', $to);
        }

        if ($types) {
            $query->whereIn('type', $types);
        }

        return $query->get();
    }

    public function countForDevices(Collection $deviceIds, ?Carbon $from = null, ?array $types = null): int
    {
        if ($deviceIds->isEmpty() || ! $this->tableExists()) {
            return 0;
        }

        $query = VehicleEvent::query()->whereIn('device_id', $deviceIds);

        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($types) {
            $query->whereIn('type', $types);
        }

        return $query->count();
    }

    public function countSince(Carbon $from): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        return VehicleEvent::query()->where('occurred_at', '>=', $from)->count();
    }

    public function recentForDevices(Collection $deviceIds, int $limit = 12): Collection
    {
        if ($deviceIds->isEmpty() || ! $this->tableExists()) {
            return collect();
        }

        return VehicleEvent::query()
            ->whereIn('device_id', $deviceIds)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('vehicle_events');
    }
}
