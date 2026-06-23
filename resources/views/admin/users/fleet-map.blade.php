@extends('admin.layouts.app')
@section('title', __('app.admin.users.fleet_map_page_title', ['name' => $targetUser->name]))
@section('page-title', __('app.admin.users.fleet_map_page_title', ['name' => $targetUser->name]))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/fleet-map.css') }}?v={{ filemtime(public_path('css/fleet-map.css')) }}">
    <style>
        .content-wrap { padding: 0 !important; }
        .footer-premium { display: none; }
        .user-fleet-map-page {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 64px);
            min-height: 420px;
        }
        .user-fleet-map-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: #fff;
            border-bottom: 1px solid var(--admin-border, #E0E0E0);
            z-index: 2;
        }
        .user-fleet-map-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
            font-size: 0.8125rem;
            color: var(--admin-text-light, #718096);
        }
        .user-fleet-map-stats strong { color: var(--admin-text, #2D3748); }
        .user-fleet-map-body {
            position: relative;
            flex: 1;
            min-height: 0;
        }
        #userFleetMap {
            width: 100%;
            height: 100%;
        }
        .user-fleet-map-controls {
            position: absolute;
            top: 12px;
            inset-inline-end: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 5;
        }
        .user-fleet-map-controls .btn {
            width: 42px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);
        }
        .user-fleet-map-empty {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(248, 250, 252, 0.92);
            z-index: 4;
            text-align: center;
            padding: 2rem;
        }
    </style>
@endpush

@section('content')
    <div class="user-fleet-map-page">
        <div class="user-fleet-map-toolbar">
            <div>
                <a href="{{ route($panel . '.users.index') }}" class="btn btn-sm btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>{{ __('app.admin.users.fleet_map_back') }}
                </a>
                <span class="fw-semibold">{{ $targetUser->name }}</span>
                <span class="text-muted small ms-1"><x-admin.ltr>{{ $targetUser->email }}</x-admin.ltr></span>
            </div>
            <div class="user-fleet-map-stats">
                <span>{{ __('app.admin.locations.total_devices') }}: <strong data-fleet-stat="totalDevices">{{ $stats['totalDevices'] ?? 0 }}</strong></span>
                <span>{{ __('app.admin.locations.online_now') }}: <strong data-fleet-stat="onlineNow">{{ $stats['onlineNow'] ?? 0 }}</strong></span>
                <span>{{ __('app.admin.locations.moving') }}: <strong data-fleet-stat="running">{{ $stats['running'] ?? 0 }}</strong></span>
                <span>{{ __('app.admin.locations.offline') }}: <strong data-fleet-stat="offlineNow">{{ $stats['offlineNow'] ?? 0 }}</strong></span>
            </div>
        </div>

        <div class="user-fleet-map-body">
            <div id="userFleetMap" aria-label="{{ __('app.admin.users.fleet_map_aria') }}"></div>

            @php
                $onMapCount = collect($initialPayload)->filter(fn ($d) => $d['lat'] !== null && $d['lng'] !== null)->count();
            @endphp
            @if($onMapCount === 0)
                <div class="user-fleet-map-empty">
                    <div>
                        <i class="fas fa-map-marked-alt fa-2x text-muted mb-3"></i>
                        <p class="mb-0 text-muted">{{ __('app.admin.users.fleet_map_no_positions') }}</p>
                    </div>
                </div>
            @endif

            <div class="user-fleet-map-controls">
                <button type="button" class="btn btn-light" id="btnFitFleet" title="{{ __('app.admin.users.fleet_map_fit') }}">
                    <i class="fas fa-compress-arrows-alt"></i>
                </button>
                <button type="button" class="btn btn-light" id="btnRefreshFleet" title="{{ __('app.admin.users.fleet_map_refresh') }}">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.USER_FLEET_MAP_CONFIG = {
            googleMapsKey: @json(config('services.google.maps_key')),
            liveJsonUrl: @json(route($panel . '.users.fleet-map.live-json', $targetUser)),
            pollIntervalMs: 5000,
            devices: @json($initialPayload),
            stats: @json($stats),
            mapSpec: @json($mapSpec),
        };
    </script>
    <script src="{{ protected_js('vehicle-marker.js') }}"></script>
    <script src="{{ protected_js('fleet-map-cluster.js') }}"></script>
    <script src="{{ protected_js('user-fleet-map.js') }}"></script>
@endpush
