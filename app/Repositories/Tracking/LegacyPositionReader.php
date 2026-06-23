<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Models\Device;
use App\Models\DeviceLocation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LegacyPositionReader implements PositionReaderInterface
{
    public function latestForDevice(Device $device): ?DeviceLocation
    {
        if (! $this->tableExists()) {
            return null;
        }

        return DeviceLocation::query()
            ->where('device_id', $device->id)
            ->orderByDesc('recorded_at')
            ->first();
    }

    public function historyForDevice(
        Device $device,
        ?Carbon $from = null,
        ?Carbon $to = null,
        string $order = 'asc'
    ): Collection {
        if (! $this->tableExists()) {
            return collect();
        }

        $query = DeviceLocation::query()->where('device_id', $device->id);

        if ($from) {
            $query->where('recorded_at', '>=', $from);
        }

        if ($to) {
            $query->where('recorded_at', '<=', $to);
        }

        $direction = strtolower($order) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('recorded_at', $direction)->orderBy('id', $direction)->get();
    }

    public function previousBefore(Device $device, int $excludeLocationId, ?int $excludeTraccarPositionId = null): ?DeviceLocation
    {
        if (! $this->tableExists()) {
            return null;
        }

        return DeviceLocation::query()
            ->where('device_id', $device->id)
            ->where('id', '!=', $excludeLocationId)
            ->orderByDesc('recorded_at')
            ->first();
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('device_locations');
    }
}
