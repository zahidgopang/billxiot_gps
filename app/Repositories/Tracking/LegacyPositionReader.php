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

    /**
     * @param  list<int>  $deviceIds
     * @return array<int, DeviceLocation>
     */
    public function latestForDevices(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if ($deviceIds === [] || ! $this->tableExists()) {
            return [];
        }

        $rows = DeviceLocation::query()
            ->whereIn('device_id', $deviceIds)
            ->whereIn('id', function ($query) use ($deviceIds) {
                $query->selectRaw('MAX(id)')
                    ->from('device_locations')
                    ->whereIn('device_id', $deviceIds)
                    ->groupBy('device_id');
            })
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->device_id] = $row;
        }

        return $out;
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

    /**
     * @param  list<Device>  $devices
     * @return array<int, Collection<int, DeviceLocation>>
     */
    public function historyForDevices(
        array $devices,
        ?Carbon $from = null,
        ?Carbon $to = null,
        string $order = 'asc'
    ): array {
        if (! $this->tableExists() || $devices === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(fn (Device $d) => (int) $d->id, $devices)));
        $query = DeviceLocation::query()->whereIn('device_id', $ids);

        if ($from) {
            $query->where('recorded_at', '>=', $from);
        }

        if ($to) {
            $query->where('recorded_at', '<=', $to);
        }

        $direction = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $rows = $query->orderBy('device_id', $direction)
            ->orderBy('recorded_at', $direction)
            ->orderBy('id', $direction)
            ->get();

        $grouped = [];
        foreach ($ids as $id) {
            $grouped[$id] = collect();
        }

        foreach ($rows as $row) {
            $grouped[(int) $row->device_id]->push($row);
        }

        return $grouped;
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
