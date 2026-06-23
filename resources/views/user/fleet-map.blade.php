@extends('user.layout_user')

@section('title', __('app.user.devices.fleet_map_title') . ' - ' . __('app.brand'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/fleet-map.css') }}?v={{ filemtime(public_path('css/fleet-map.css')) }}">
    <style>
        body.user-fleet-map-page-active {
            overflow: hidden;
        }

        body.user-fleet-map-page-active .content-wrap {
            height: calc(100dvh - 96px);
            max-height: calc(100dvh - 96px);
            overflow: hidden;
            padding: 0 !important;
        }

        @media (max-width: 768px) {
            body.user-fleet-map-page-active .content-wrap {
                height: calc(100dvh - 88px);
                max-height: calc(100dvh - 88px);
            }
        }

        .user-fleet-map-page {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
            background: var(--light-bg, #F8FAFC);
        }

        .user-fleet-map-toolbar {
            flex-shrink: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            background: var(--card-bg, #fff);
            border-bottom: 1px solid var(--border-color, #E0E0E0);
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
        }

        .user-fleet-map-toolbar__title {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .user-fleet-map-toolbar__title .fleet-map-heading {
            font-weight: 700;
            color: var(--text-primary, #2D3748);
        }

        .user-fleet-map-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.85rem;
            font-size: 0.8125rem;
            color: var(--text-secondary, #718096);
        }

        .user-fleet-map-stats span {
            white-space: nowrap;
        }

        .user-fleet-map-stats strong {
            color: var(--text-primary, #2D3748);
        }

        .user-fleet-map-body {
            position: relative;
            flex: 1;
            min-height: 280px;
            overflow: hidden;
        }

        #userFleetMap {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            background: #e8eef4;
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
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .user-fleet-map-empty,
        .user-fleet-map-error {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            z-index: 4;
            pointer-events: none;
        }

        .user-fleet-map-empty {
            background: rgba(248, 250, 252, 0.88);
        }

        .user-fleet-map-error {
            background: rgba(254, 242, 242, 0.94);
            pointer-events: auto;
        }

        .user-fleet-map-error[hidden],
        .user-fleet-map-empty[hidden] {
            display: none !important;
        }

        .user-fleet-map-error .alert {
            max-width: 420px;
            margin: 0;
        }
    </style>
@endpush

@section('content')
    <div class="user-fleet-map-page">
        <div class="user-fleet-map-toolbar">
            <div class="user-fleet-map-toolbar__title">
                <a href="{{ route('user.devices.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>{{ __('app.user.devices.fleet_map_back') }}
                </a>
                <span class="fleet-map-heading">{{ __('app.user.devices.fleet_map_title') }}</span>
            </div>
            <div class="user-fleet-map-stats">
                <span>{{ __('app.user.devices.total') }}: <strong data-fleet-stat="totalDevices">{{ $stats['totalDevices'] ?? 0 }}</strong></span>
                <span>{{ __('app.user.devices.online_now') }}: <strong data-fleet-stat="onlineNow">{{ $stats['onlineNow'] ?? 0 }}</strong></span>
                <span>{{ __('app.user.devices.moving') }}: <strong data-fleet-stat="running">{{ $stats['running'] ?? 0 }}</strong></span>
                <span>{{ __('app.common.offline') }}: <strong data-fleet-stat="offlineNow">{{ $stats['offlineNow'] ?? 0 }}</strong></span>
            </div>
        </div>

        <div class="user-fleet-map-body">
            <div id="userFleetMap" aria-label="{{ __('app.user.devices.fleet_map_aria') }}"></div>

            <div id="userFleetMapError" class="user-fleet-map-error" hidden>
                <div class="alert alert-danger mb-0" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span data-fleet-error-text>{{ __('app.map.loading_map_failed') }}</span>
                </div>
            </div>

            @php
                $onMapCount = collect($initialPayload)->filter(fn ($d) => $d['lat'] !== null && $d['lng'] !== null)->count();
            @endphp
            <div class="user-fleet-map-empty" @if($onMapCount > 0) hidden @endif>
                <div>
                    <i class="fas fa-map-marked-alt fa-2x text-muted mb-3 d-block"></i>
                    <p class="mb-0 text-muted">{{ __('app.user.devices.fleet_map_no_positions') }}</p>
                </div>
            </div>

            <div class="user-fleet-map-controls">
                <button type="button" class="btn btn-light" id="btnFitFleet" title="{{ __('app.user.devices.fleet_map_fit') }}">
                    <i class="fas fa-compress-arrows-alt"></i>
                </button>
                <button type="button" class="btn btn-light" id="btnRefreshFleet" title="{{ __('app.user.devices.fleet_map_refresh') }}">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>document.body.classList.add('user-fleet-map-page-active');</script>
    <script>
        window.USER_FLEET_MAP_CONFIG = {
            googleMapsKey: @json(config('services.google.maps_key')),
            liveJsonUrl: @json(route('user.devices.fleet-map.live-json')),
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
