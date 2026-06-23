@forelse($recentDevices as $device)
    @php
        $status = $dashboardService->resolveDeviceStatus($device, $alertDeviceIds ?? null);
        $latest = $device->latestLocation;
        $battery = $latest?->battery_level;
        $batteryPercent = is_numeric($battery) ? min(100, max(0, (int) $battery)) : null;
        $iconClass = $device->deviceTypeIconClass();
        $canTrack = app(\App\Services\DeviceAccessService::class)->canUseMap(auth()->user(), $device);
        $iconColor = match($status['class']) {
            'bg-success' => 'text-success',
            'bg-warning' => 'text-warning',
            'bg-danger' => 'text-danger',
            default => 'text-info',
        };
    @endphp
    <tr>
        <td>
            <div class="d-flex align-items-center">
                <div class="vehicle-icon me-2">
                    <i class="fas {{ $iconClass }} {{ $iconColor }}"></i>
                </div>
                <div class="vehicle-list-identity">
                    <span class="vehicle-list-name">{{ $device->listPrimaryLabel() }}</span>
                    @if($plate = $device->listSecondaryLabel())
                        <span class="vehicle-list-plate"><x-admin.ltr>{{ $plate }}</x-admin.ltr></span>
                    @endif
                </div>
            </div>
        </td>
        <td>
            <span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span>
        </td>
        <td>
            @if($latest)
                {{ number_format((float) $latest->lat, 5) }}, {{ number_format((float) $latest->lng, 5) }}
            @else
                <span class="text-muted">No location</span>
            @endif
        </td>
        <td>{{ $latest ? number_format((float) ($latest->speed ?? 0), 0) . ' km/h' : '—' }}</td>
        <td>
            @if($batteryPercent !== null)
                <div class="progress" style="height: 8px; width: 80px;">
                    <div class="progress-bar {{ $batteryPercent < 20 ? 'bg-danger' : ($batteryPercent < 50 ? 'bg-warning' : 'bg-success') }}"
                         style="width: {{ $batteryPercent }}%;"></div>
                </div>
            @else
                <span class="text-muted small">N/A</span>
            @endif
        </td>
        <td>{{ $latest?->recorded_at?->diffForHumans() ?? '—' }}</td>
        <td>
            @if($canTrack)
                <a href="{{ $device->launchMapRoute() }}" class="btn btn-outline-premium btn-sm me-1" title="Live map">
                    <i class="fas fa-map-marked-alt"></i>
                </a>
            @else
                <span class="badge bg-secondary" title="Map unavailable"><i class="fas fa-lock"></i></span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="text-center text-muted py-4">
            <i class="fas fa-car fa-2x mb-2"></i>
            <p class="mb-0">{{ __('app.user.dashboard.no_devices_yet') }}</p>
        </td>
    </tr>
@endforelse
