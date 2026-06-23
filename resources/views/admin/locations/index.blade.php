@extends('admin.layouts.app')
@section('title', __('app.admin.locations.title'))
@section('page-title', __('app.admin.locations.title'))

@push('styles')
    <style>
        .device-type-icon { width: 36px; text-align: center; }
        .live-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        tr.row-updated { transition: background-color 0.4s ease; background-color: rgba(25, 118, 210, 0.06); }
        .stat-pulse { transition: transform 0.25s ease; transform: scale(1.06); }
    </style>
@endpush

@section('content')
    <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-1">{{ __('app.admin.locations.title') }}</h5>
                <p class="text-muted small mb-0">All fleet devices — open live map with full admin access (no subscription limits).</p>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-3 col-6">
                <div class="border rounded p-2 text-center">
                    <div class="fw-bold" data-stat="totalDevices">{{ $stats['totalDevices'] ?? 0 }}</div>
                    <small class="text-muted">{{ __('app.admin.locations.total_devices') }}</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="border rounded p-2 text-center">
                    <div class="fw-bold text-success" data-stat="onlineNow">{{ $stats['onlineNow'] ?? 0 }}</div>
                    <small class="text-muted">{{ __('app.admin.locations.online_now') }}</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="border rounded p-2 text-center">
                    <div class="fw-bold text-primary" data-stat="running">{{ $stats['running'] ?? 0 }}</div>
                    <small class="text-muted">{{ __('app.admin.locations.moving') }}</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="border rounded p-2 text-center">
                    <div class="fw-bold text-secondary" data-stat="offlineNow">{{ $stats['offlineNow'] ?? 0 }}</div>
                    <small class="text-muted">{{ __('app.admin.locations.offline') }}</small>
                </div>
            </div>
        </div>

        <form method="GET" class="admin-filter-bar row g-2 mb-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1" for="locations-filter-q">{{ __('app.common.search') }}</label>
                <input name="q" id="locations-filter-q" value="{{ request('q') }}" class="form-control form-control-sm admin-ltr" dir="ltr"
                       placeholder="{{ __('app.admin.locations.search_placeholder') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="locations-filter-status">{{ __('app.common.status') }}</label>
                <select name="status" id="locations-filter-status" class="form-select form-select-sm" data-search="false"
                        data-placeholder="{{ __('app.admin.locations.all_statuses') }}">
                    <option value="">{{ __('app.admin.locations.all_statuses') }}</option>
                    <option value="active" @selected(request('status') === 'active')>{{ __('app.common.active') }}</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>{{ __('app.common.inactive') }}</option>
                    <option value="blocked" @selected(request('status') === 'blocked')>{{ __('app.common.blocked') }}</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-1 admin-filter-actions">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">{{ __('app.common.filter') }}</button>
                @if(request()->filled('q') || request()->filled('status'))
                    <a href="{{ route(request()->routeIs('client.*') ? 'client.locations.index' : 'admin.locations.index') }}"
                       class="btn btn-outline-secondary btn-sm" title="{{ __('app.common.clear') }}">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>{{ __('app.admin.locations.device') }}</th>
                    <th>{{ __('app.admin.devices.type') }}</th>
                    <th>{{ __('app.admin.locations.owner') }}</th>
                    <th>{{ __('app.admin.locations.live_status') }}</th>
                    <th>{{ __('app.admin.locations.last_position') }}</th>
                    <th>{{ __('app.admin.locations.speed') }}</th>
                    <th>{{ __('app.admin.locations.last_update') }}</th>
                    <th>{{ __('app.admin.locations.subscription') }}</th>
                    <th class="text-end">{{ __('app.common.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($devices as $d)
                    @php
                        $liveStatus = $dashboardService->resolveDeviceStatus($d, $alertDeviceIds);
                        $latest = $d->latestLocation;
                        $subStatus = $subscriptionService->statusLabel($d);
                    @endphp
                    <tr data-device-id="{{ $d->id }}">
                        <td>
                            <strong>{{ $d->listPrimaryLabel() }}</strong>
                            <small class="d-block text-muted"><x-admin.ltr tag="code">{{ $d->imei }}</x-admin.ltr></small>
                            @if($plate = $d->listSecondaryLabel())
                                <small class="d-block text-muted"><x-admin.ltr>{{ $plate }}</x-admin.ltr></small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $d->deviceTypeLabel() }}</span>
                            @if($d->vehicle_type)
                                <small class="d-block text-muted mt-1">{{ $d->vehicleTypeLabel() }}</small>
                            @endif
                        </td>
                        <td>
                            @if($d->user)
                                <span>{{ $d->user->name }}</span>
                                <small class="d-block text-muted"><x-admin.ltr>{{ $d->user->email }}</x-admin.ltr></small>
                            @else
                                <span class="text-muted">{{ __('app.admin.locations.unassigned') }}</span>
                            @endif
                        </td>
                        <td data-field="live-status">
                            <span class="live-dot {{ $liveStatus['dot'] }} me-1"></span>
                            <span class="badge {{ $liveStatus['class'] }}">{{ $liveStatus['label'] }}</span>
                        </td>
                        <td data-field="last-position">
                            @if($latest)
                                <x-admin.ltr>{{ number_format((float) $latest->lat, 5) }}, {{ number_format((float) $latest->lng, 5) }}</x-admin.ltr>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td data-field="speed">
                            @if($latest)
                                <x-admin.ltr>{{ number_format((float) ($latest->speed ?? 0), 0) }} {{ __('app.admin.locations.kmh') }}</x-admin.ltr>
                            @else
                                —
                            @endif
                        </td>
                        <td data-field="last-update">
                            <small><x-admin.ltr>{{ $latest?->recorded_at?->diffForHumans() ?? __('app.common.no_data') }}</x-admin.ltr></small>
                        </td>
                        <td>
                            <span class="badge {{ $subStatus['class'] }}">{{ $subStatus['label'] }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ $d->launchMapRoute(true) }}"
                               class="btn btn-sm btn-primary"
                               title="Open live map (admin full access)">
                                <i class="fas fa-map-marked-alt me-1"></i> Map
                            </a>
                            <a href="{{ route(($panel ?? 'admin') . '.devices.edit', $d) }}"
                               class="btn btn-sm btn-outline-secondary"
                               title="Edit device">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No devices found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $devices->links() }}</div>
    </div>
@endsection

@push('scripts')
    <script>
        window.ADMIN_LOCATIONS_LIVE = {
            pollUrl: @json(route(($panel ?? 'admin') . '.locations.live-json')),
            pollMs: 5000,
            dash: @json(__('app.map.dash')),
            noData: @json(__('app.common.no_data')),
            kmh: @json(__('app.admin.locations.kmh')),
        };
    </script>
    <script src="{{ protected_js('admin-locations-live.js') }}"></script>
@endpush
