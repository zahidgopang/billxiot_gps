<div class="activity-list">
    @forelse($activities as $activity)
        <div class="activity-card">
            <div class="d-flex">
                <div class="activity-icon me-3" style="background: {{ $activity['gradient'] ?? 'rgba(25, 118, 210, 0.1)' }}; color: #fff;">
                    <i class="fas {{ $activity['icon'] ?? 'fa-info-circle' }}"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">{{ $activity['title'] }}</h6>
                    <p class="text-muted mb-1">{{ $activity['description'] }}</p>
                    <small class="text-accent">
                        @if($activity['time'] ?? null)
                            {{ $activity['time']->diffForHumans() }}
                        @else
                            Recently
                        @endif
                    </small>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-4">
            <i class="fas fa-history fa-2x mb-2 opacity-50"></i>
            <p class="mb-0">{{ __('app.user.dashboard.no_activity_hint') }}</p>
        </div>
    @endforelse
</div>
