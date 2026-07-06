@forelse($recentDevices as $device)
    @php
        $status = $dashboardService->resolveDeviceStatus($device, $alertDeviceIds ?? null);
        $latest = $device->latestLocation;
        $battery = $latest?->battery_level;
        $batteryPercent = is_numeric($battery) ? min(100, max(0, (int) $battery)) : null;
        $canTrack = app(\App\Services\DeviceAccessService::class)->canUseMap(auth()->user(), $device);
        $badgeClass = match ($status['key'] ?? '') {
            'running', 'moving' => 'ud-badge--ok',
            'offline', 'blocked' => 'ud-badge--muted',
            'alert' => 'ud-badge--danger',
            default => 'ud-badge--warn',
        };
    @endphp
    <tr>
        <td>
            <div class="vehicle-list-identity">
                <span class="vehicle-list-name">{{ $device->listPrimaryLabel() }}</span>
                @if($plate = $device->listSecondaryLabel())
                    <span class="vehicle-list-plate"><x-admin.ltr>{{ $plate }}</x-admin.ltr></span>
                @endif
            </div>
        </td>
        <td><span class="ud-badge {{ $badgeClass }}">{{ $status['label'] }}</span></td>
        <td>
            @if($latest)
                <span class="admin-ltr" dir="ltr">{{ number_format((float) $latest->lat, 5) }}, {{ number_format((float) $latest->lng, 5) }}</span>
            @else
                <span style="color: var(--apple-muted);">—</span>
            @endif
        </td>
        <td>{{ $latest ? number_format((float) ($latest->speed ?? 0), 0) . ' km/h' : '—' }}</td>
        <td>
            @if($batteryPercent !== null)
                {{ $batteryPercent }}%
            @else
                <span style="color: var(--apple-muted);">N/A</span>
            @endif
        </td>
        <td>{{ $latest?->recorded_at?->diffForHumans() ?? '—' }}</td>
        <td>
            @if($canTrack)
                <a href="{{ $device->launchMapRoute() }}" class="ud-btn ud-btn--secondary" style="padding: 6px 10px; font-size: 12px;" title="{{ __('app.common.map') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                </a>
            @else
                <span class="ud-badge ud-badge--muted">—</span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="text-center" style="color: var(--apple-muted); padding: 32px;">
            {{ __('app.user.dashboard.no_devices_yet') }}
        </td>
    </tr>
@endforelse
