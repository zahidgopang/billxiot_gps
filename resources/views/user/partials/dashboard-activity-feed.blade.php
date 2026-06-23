@forelse($activities as $activity)
    <div class="activity-item mb-3 p-3 premium-card">
        <div class="d-flex">
            <div class="activity-icon me-3">
                <div class="icon-box-sm" style="background: {{ $activity['gradient'] }};">
                    <i class="fas {{ $activity['icon'] }} text-white"></i>
                </div>
            </div>
            <div class="flex-grow-1">
                <h6 class="mb-1">{{ $activity['title'] }}</h6>
                <p class="text-muted mb-1">{{ $activity['description'] }}</p>
                <small style="color: var(--primary-blue);">
                    {{ $activity['time']?->diffForHumans() ?? '—' }}
                </small>
            </div>
        </div>
    </div>
@empty
    <div class="text-center text-muted py-5">
        <i class="fas fa-inbox fa-2x mb-3"></i>
        <p class="mb-0">No recent activity yet.</p>
    </div>
@endforelse
