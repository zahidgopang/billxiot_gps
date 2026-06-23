<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Services\Tracking\DevicePositionLoader;
use App\Services\Traccar\TraccarTrackingGate;
use Illuminate\Support\Collection;

class FleetMapDeviceService
{
    public function __construct(
        private TraccarTrackingGate $trackingGate,
        private DevicePositionLoader $positionLoader,
    ) {}

    /**
     * End-user fleet map — active device status + active subscription only.
     *
     * @return Collection<int, Device>
     */
    public function loadForEndUser(User $user): Collection
    {
        $devices = $user->trackerDevicesQuery()
            ->with(['subscription'])
            ->orderBy('name')
            ->get();

        return $this->trackingGate
            ->filterTrackable($user, $devices, requireSubscription: true)
            ->sortBy(fn (Device $device) => mb_strtolower($device->mapMarkerTitle()))
            ->values();
    }

    /**
     * @param  Collection<int, Device>  $devices
     * @return Collection<int, Device>
     */
    public function attachPositions(Collection $devices): Collection
    {
        $this->positionLoader->attachLatestToMany($devices);

        return $devices;
    }

    /**
     * @param  Collection<int, Device>  $devices
     * @return list<array<string, mixed>>
     */
    public function buildPayload(Collection $devices, UserDashboardService $dashboard, callable $launchUrl): array
    {
        $alertDeviceIds = $dashboard->alertDeviceIds($devices);

        return $devices->map(function (Device $device) use ($dashboard, $alertDeviceIds, $launchUrl) {
            $latest = $device->latestLocation;
            $status = $dashboard->resolveDeviceStatus($device, $alertDeviceIds);

            return [
                'id' => $device->id,
                'title' => $device->mapMarkerTitle(),
                'plate' => $device->mapMarkerPlateLine(),
                'vehicle_type' => $device->vehicle_type ?: 'car',
                'status_key' => $status['key'],
                'status_label' => $status['label'],
                'lat' => $latest ? (float) $latest->lat : null,
                'lng' => $latest ? (float) $latest->lng : null,
                'heading' => $latest ? (float) ($latest->heading ?? 0) : 0,
                'speed' => $latest ? round((float) ($latest->speed ?? 0)) : null,
                'recorded_at_human' => $latest?->recorded_at
                    ? app_datetime_format($latest->recorded_at)
                    : __('app.user.devices.no_data_yet'),
                'launch_map_url' => $launchUrl($device),
            ];
        })->values()->all();
    }
}
