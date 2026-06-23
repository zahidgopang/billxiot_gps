<div class="profile-fleet-status mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted small fw-semibold me-1">Fleet status</span>
        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">
            <i class="fas fa-check-circle me-1"></i>{{ $activeDevices }} active
        </span>
        @if(($inactiveDevices ?? 0) > 0)
            <span class="badge rounded-pill bg-secondary-subtle text-secondary border">
                <i class="fas fa-pause-circle me-1"></i>{{ $inactiveDevices }} inactive
            </span>
        @endif
        @if(($blockedDevices ?? 0) > 0)
            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle">
                <i class="fas fa-ban me-1"></i>{{ $blockedDevices }} blocked
            </span>
        @endif
        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle">
            <i class="fas fa-wifi me-1"></i>{{ $onlineNow }} online
        </span>
        @if(($offlineNow ?? 0) > 0)
            <span class="badge rounded-pill bg-light text-muted border">
                <i class="fas fa-plug me-1"></i>{{ $offlineNow }} offline
            </span>
        @endif
        @if(($running ?? 0) > 0)
            <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle">
                <i class="fas fa-car me-1"></i>{{ $running }} moving
            </span>
        @endif
        @if(($parked ?? 0) > 0)
            <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle">
                <i class="fas fa-parking me-1"></i>{{ $parked }} parked
            </span>
        @endif
        @if(($alerts ?? 0) > 0)
            <span class="badge rounded-pill bg-danger">
                <i class="fas fa-exclamation-triangle me-1"></i>{{ $alerts }} alert{{ $alerts === 1 ? '' : 's' }} (24h)
            </span>
        @endif
        <span class="badge rounded-pill bg-light text-muted border ms-md-auto">
            <i class="fas fa-user-clock me-1"></i>Member {{ $memberDays }} {{ Str::plural('day', $memberDays) }}
        </span>
    </div>
</div>
