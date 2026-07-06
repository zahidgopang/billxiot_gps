@forelse($activities as $activity)
    @php
        $dot = match (true) {
            str_contains($activity['icon'] ?? '', 'exclamation-triangle') => '#FF9F0A',
            str_contains($activity['icon'] ?? '', 'exclamation-circle') => '#FF3B30',
            str_contains($activity['icon'] ?? '', 'parking') => '#8E8E93',
            str_contains($activity['icon'] ?? '', 'draw-polygon') => '#007AFF',
            default => '#34C759',
        };
        $timeLabel = $activity['time']?->format('H:i') ?? '—';
    @endphp
    <div class="ud-timeline-item">
        <div class="ud-timeline-time">{{ $timeLabel }}</div>
        <div class="ud-timeline-dot" style="background: {{ $dot }};"></div>
        <div>
            <p class="ud-timeline-title">{{ $activity['title'] }}</p>
            @if(!empty($activity['description']))
                <p class="ud-timeline-desc">{{ $activity['description'] }}</p>
            @endif
        </div>
    </div>
@empty
    <div class="ud-map-empty" style="height: 200px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
        <p class="mb-0">{{ __('app.user.dashboard.no_activity') }}</p>
    </div>
@endforelse
