{{-- Fleet stats — expects UserDashboardService profile variables --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(25, 118, 210, 0.1); color: var(--primary-blue);">
            <i class="fas fa-satellite-dish"></i>
        </div>
        <div class="stat-value">{{ $totalDevices }}</div>
        <div class="stat-label">Total Devices</div>
        <small class="text-muted d-block mt-1">{{ $activeDevices }} active registered</small>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
            <i class="fas fa-signal"></i>
        </div>
        <div class="stat-value">{{ $onlineNow }}</div>
        <div class="stat-label">Online Now</div>
        <small class="text-muted d-block mt-1">Last {{ \App\Services\UserDashboardService::ONLINE_MINUTES }} min</small>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
            <i class="fas fa-route"></i>
        </div>
        <div class="stat-value">{{ number_format($totalDistanceKm) }}</div>
        <div class="stat-label">KM Tracked</div>
        <small class="text-muted d-block mt-1">Last 30 days</small>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
            <i class="fas fa-bell"></i>
        </div>
        <div class="stat-value">{{ $activeAlerts }}</div>
        <div class="stat-label">Geofence Alerts</div>
        <small class="text-muted d-block mt-1">Zone exits (7 days)</small>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-value">{{ $trackingDaysActive }}</div>
        <div class="stat-label">Tracking Days</div>
        <small class="text-muted d-block mt-1">With GPS data (30 days)</small>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #7c3aed;">
            <i class="fas fa-draw-polygon"></i>
        </div>
        <div class="stat-value">{{ $geofenceCount }}</div>
        <div class="stat-label">Geofences</div>
        <small class="text-muted d-block mt-1">{{ $running }} moving · {{ $parked }} parked</small>
    </div>
</div>
