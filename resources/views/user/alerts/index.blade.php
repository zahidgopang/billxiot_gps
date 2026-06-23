@extends('user.layout_user')

@section('title', 'Vehicle Alerts — FalconEyeGPS')

@push('styles')
<style>
    .alert-type-badge {
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.35em 0.65em;
        border-radius: 8px;
        white-space: nowrap;
    }
    .alert-type-badge--error { background: rgba(239, 68, 68, 0.12); color: #dc2626; }
    .alert-type-badge--warning { background: rgba(245, 158, 11, 0.12); color: #d97706; }
    .alert-type-badge--info { background: rgba(59, 130, 246, 0.12); color: #2563eb; }
    .alerts-table td { vertical-align: middle; font-size: 0.9rem; }
    .alerts-table .event-msg { color: var(--text-secondary); font-size: 0.85rem; max-width: 320px; }
    .alerts-filters .form-control, .alerts-filters .form-select {
        border-radius: 10px;
        border: 2px solid rgba(0,0,0,0.08);
    }
</style>
@endpush

@section('content')
<div class="container dashboard-container">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-2">
                <i class="fas fa-bell me-2" style="color: var(--primary-blue);"></i>
                Vehicle Alerts &amp; Events
            </h4>
            <p class="text-muted mb-0">
                Stops, movement, overspeed, slow speed, geofence enter/exit, and device warnings.
            </p>
        </div>
        <a href="{{ route('user.devices.index') }}" class="btn btn-outline-premium">
            <i class="fas fa-satellite me-2"></i> Devices
        </a>
    </div>

    <div class="premium-card mb-4">
        <form method="GET" action="{{ route('user.alerts.index') }}" class="alerts-filters">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Device</label>
                    <select name="device_id" class="form-select" data-placeholder="All devices">
                        <option value="">All devices</option>
                        @foreach($devices as $d)
                            <option value="{{ $d->id }}" @selected(request('device_id') == $d->id)>
                                {{ $d->name }} ({{ $d->imei }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Event type</label>
                    <select name="type" class="form-select" data-placeholder="All event types">
                        <option value="">All types</option>
                        @foreach($eventTypes as $t)
                            <option value="{{ $t }}" @selected(request('type') === $t)>
                                {{ \App\Models\VehicleEvent::make(['type' => $t])->typeLabel() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">From</label>
                    <x-admin.date-input name="from" id="alerts-from" :value="request('from')" input-class="form-control" />
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">To</label>
                    <x-admin.date-input name="to" id="alerts-to" :value="request('to')" input-class="form-control" />
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-premium flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('user.alerts.index') }}" class="btn btn-outline-secondary" title="Clear">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <div class="premium-card">
        <div class="table-responsive">
            <table class="table table-hover alerts-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Device</th>
                        <th>Event</th>
                        <th>Details</th>
                        <th class="text-end">Speed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        @php $severity = $event->severity(); @endphp
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ app_datetime_format($event->occurred_at, 'date') }}</td>
                            <td class="text-nowrap">{{ app_datetime_format($event->occurred_at, 'time') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $event->device?->name ?? '—' }}</div>
                                <small class="text-muted d-block">{{ $event->device?->imei }}</small>
                            </td>
                            <td>
                                <span class="alert-type-badge alert-type-badge--{{ $severity }}">
                                    {{ $event->typeLabel() }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $event->title }}</div>
                                <div class="event-msg">{{ $event->message }}</div>
                                @if($event->geofence)
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-draw-polygon me-1"></i>{{ $event->geofence->name }}
                                    </small>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($event->speed !== null)
                                    {{ number_format($event->speed, 0) }} km/h
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fas fa-bell-slash fa-2x mb-3 opacity-50 d-block"></i>
                                No alerts found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div class="p-3 border-top">
                {{ $events->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
