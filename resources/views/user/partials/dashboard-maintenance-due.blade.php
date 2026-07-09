@php
    $maintenanceDue = $maintenanceDue ?? ['overdue' => 0, 'soon' => 0, 'items' => []];
    $dueItems = $maintenanceDue['items'] ?? [];
    $overdueCount = (int) ($maintenanceDue['overdue'] ?? 0);
    $soonCount = (int) ($maintenanceDue['soon'] ?? 0);
@endphp
@if(($overdueCount + $soonCount) > 0)
    <div class="ud-card" style="margin-bottom: 24px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="ud-card-title mb-0">{{ __('app.user.dashboard.maintenance_due') }}</h2>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($overdueCount > 0)
                    <span class="badge bg-danger">{{ __('app.user.dashboard.maintenance_overdue_count', ['count' => $overdueCount]) }}</span>
                @endif
                @if($soonCount > 0)
                    <span class="badge bg-warning text-dark">{{ __('app.user.dashboard.maintenance_soon_count', ['count' => $soonCount]) }}</span>
                @endif
                <a href="{{ route('tracking.maintenance.index') }}" class="ud-btn ud-btn--secondary">{{ __('app.user.dashboard.maintenance_open') }}</a>
            </div>
        </div>
        <ul class="list-unstyled mb-0">
            @foreach($dueItems as $item)
                @php
                    $objNames = collect($item['objects'] ?? [])->pluck('name')->filter()->implode(', ');
                    $left = $item['odometer_left_label'] ?? $item['days_left_label'] ?? null;
                    $isOverdue = ($item['status'] ?? '') === 'overdue';
                @endphp
                <li class="d-flex justify-content-between align-items-start gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div>
                        <div class="fw-semibold">{{ $item['name'] ?? '—' }}</div>
                        <div class="small text-muted">{{ $objNames !== '' ? $objNames : '—' }}</div>
                    </div>
                    <div class="text-end small {{ $isOverdue ? 'text-danger fw-semibold' : 'text-warning' }}">
                        {{ $left ?: ($isOverdue ? __('app.tracking.maint_status_overdue') : __('app.tracking.maint_status_soon')) }}
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
