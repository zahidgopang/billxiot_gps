@extends('user.layout_user')

@section('title', __('app.user.devices.title') . ' - ' . __('app.brand'))

@php
    $mapIconAuth = app(\App\Services\Tracking\DeviceMapIconAuthorization::class);
    $devicesUser = auth()->user();
    $canChangeIcons = $devicesUser && $mapIconAuth->hasAnyAppearancePermission($devicesUser);
    $iconDevice = $canChangeIcons ? $devices->first() : null;
@endphp

    @push('styles')
    <style>
        tr.device-row.row-updated { transition: background-color 0.4s ease; background-color: rgba(37, 99, 235, 0.06); }
        .stat-pulse { transition: transform 0.25s ease; transform: scale(1.06); }
        .device-icon-check { width: 1.1rem; height: 1.1rem; cursor: pointer; }
        #iconBulkBar { display: none; }
        #iconBulkBar.is-visible { display: flex; }
        /* Device List: show ~10 rows, then scroll */
        .devices-table-scroll {
            max-height: 620px;
            overflow: auto;
            overscroll-behavior: contain;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 12px;
            scrollbar-width: thin;
        }
        .devices-table-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
        .devices-table-scroll::-webkit-scrollbar-thumb {
            background: rgba(15, 23, 42, 0.22);
            border-radius: 999px;
        }
        .devices-table-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fff;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.08);
        }
        /* Compact Track / Edit so the actions column does not wrap awkwardly */
        #devicesTable td:last-child {
            white-space: nowrap;
            width: 1%;
        }
        #devicesTable .device-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.35rem;
            flex-wrap: nowrap;
        }
        #devicesTable .device-actions .btn {
            padding: 0.2rem 0.45rem;
            font-size: 0.72rem;
            line-height: 1.2;
            border-radius: 0.4rem;
        }
        #devicesTable .device-actions .btn i {
            margin-inline-end: 0.2rem !important;
            font-size: 0.7rem;
        }
        @media (max-width: 991px) {
            #devicesTable .device-actions .btn .btn-label {
                display: none;
            }
            #devicesTable .device-actions .btn i {
                margin-inline-end: 0 !important;
            }
            #devicesTable .device-actions .btn {
                padding: 0.3rem 0.45rem;
                min-width: 2rem;
            }
        }
        .icon-vehicle-picker {
            max-height: 220px;
            overflow: auto;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 12px;
            padding: 8px 10px;
            background: #f8fafc;
        }
        .icon-vehicle-picker__item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 4px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        }
        .icon-vehicle-picker__item:last-child { border-bottom: 0; }
        .icon-vehicle-picker__item label {
            margin: 0;
            cursor: pointer;
            flex: 1;
            min-width: 0;
        }
        .icon-vehicle-picker__meta {
            display: block;
            font-size: 0.75rem;
            color: #64748b;
        }
        .device-map-icon-thumb {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 8px;
            background: #f1f5f9;
            padding: 3px;
            flex-shrink: 0;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }
        .device-row .device-icon {
            width: 40px;
            height: 40px;
            background: transparent;
            padding: 0;
        }
        .device-row .device-icon .device-map-icon-thumb {
            width: 100%;
            height: 100%;
        }
        .icon-vehicle-picker__thumb {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 6px;
            background: #fff;
            padding: 2px;
            flex-shrink: 0;
            border: 1px solid rgba(15, 23, 42, 0.08);
        }
    </style>
    @if($canChangeIcons)
        <link rel="stylesheet" href="{{ asset('css/map-marker-appearance.css') }}?v={{ @filemtime(public_path('css/map-marker-appearance.css')) ?: 1 }}">
    @endif
@endpush

@section('content')

    <div class="container dashboard-container">

        <!-- Header Section -->
        <div class="mb-4">
            <h4 class="fw-bold mb-2">
                <i class="fas fa-satellite me-2" style="color: var(--primary-blue);"></i>
                {{ __('app.user.devices.title') }}
            </h4>
            <p class="text-muted mb-0">{{ __('app.user.devices.subtitle') }}</p>
        </div>

        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));">
                            <i class="fas fa-satellite-dish text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0" id="totalDevices">{{ $totalDevices }}</h5>
                            <small class="text-muted">{{ __('app.user.devices.total') }}</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #10B981, #059669);">
                            <i class="fas fa-signal text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0" id="onlineDevices">{{ $onlineNow }}</h5>
                            <small class="text-muted">{{ __('app.user.devices.online_now') }}</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                            <i class="fas fa-car text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0" id="runningDevices">{{ $running }}</h5>
                            <small class="text-muted">{{ __('app.user.devices.moving') }}</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="premium-card">
                    <div class="d-flex align-items-center">
                        <div class="icon-box-sm me-3" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                            <i class="fas fa-parking text-white"></i>
                        </div>
                        <div>
                            <h5 class="mb-0" id="parkedDevices">{{ $parked }}</h5>
                            <small class="text-muted">{{ __('app.user.devices.parked_idle') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(($inactiveDevices ?? 0) > 0 || ($blockedDevices ?? 0) > 0 || ($alerts ?? 0) > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-3 text-muted small">
                        <span><i class="fas fa-check-circle text-success me-1"></i> {{ $activeDevices }} {{ __('app.access.registered_active') }}</span>
                        @if($inactiveDevices > 0)
                            <span><i class="fas fa-clock me-1"></i> {{ __('app.access.inactive_count', ['count' => $inactiveDevices]) }}</span>
                        @endif
                        @if($blockedDevices > 0)
                            <span><i class="fas fa-ban text-danger me-1"></i> {{ __('app.access.blocked_count', ['count' => $blockedDevices]) }}</span>
                        @endif
                        @if($alerts > 0)
                            <span><i class="fas fa-exclamation-triangle text-danger me-1"></i> {{ trans_choice('app.access.geofence_alerts_today', $alerts, ['count' => $alerts]) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if(($needsSubscriptionCount ?? 0) > 0)
            <div class="alert alert-warning d-flex align-items-start mb-4" role="status">
                <i class="fas fa-credit-card me-3 mt-1 fa-lg"></i>
                <div class="flex-grow-1">
                    <strong class="d-block mb-1">{{ __('app.user.devices.subscribe_for_map_title') }}</strong>
                    <p class="mb-0">{{ trans_choice('app.user.devices.subscribe_for_map_banner', $needsSubscriptionCount, ['count' => $needsSubscriptionCount]) }}</p>
                </div>
            </div>
        @endif

        @if(session('access_denied_message'))
            @php
                $denyReason = session('access_denied_reason', 'restricted');
                $isResubscribe = $denyReason === 'subscription_inactive';
            @endphp
            <div class="alert {{ $isResubscribe ? 'alert-warning' : 'alert-danger' }} d-flex align-items-start mb-4" role="alert">
                <i class="fas fa-{{ $isResubscribe ? 'credit-card' : 'ban' }} me-3 mt-1 fa-lg"></i>
                <div class="flex-grow-1">
                    <strong class="d-block mb-1">{{ session('access_denied_title', __('app.access.map_restricted')) }}</strong>
                    @if(session('subscription_device'))
                        <span class="d-block text-muted small mb-2">{{ __('app.access.device_label') }} <strong>{{ session('subscription_device') }}</strong></span>
                    @endif
                    <p class="mb-0">{{ session('access_denied_message') }}</p>
                    @if($isResubscribe)
                        <p class="small mb-0 mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                        {{ __('app.access.resubscribe_hint') }}
                        </p>
                    @endif
                </div>
            </div>
        @endif

        <!-- Devices Table -->
        <div class="premium-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">{{ __('app.user.devices.list_title') }}</h5>
                    <p class="text-muted mb-0">{{ __('app.user.devices.list_subtitle') }}</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @if(($fleetMapEligibleCount ?? 0) > 0)
                        <a href="{{ route('user.devices.fleet-map') }}" class="btn btn-premium btn-sm">
                            <i class="fas fa-map-marked-alt me-1"></i>{{ __('app.user.devices.fleet_map_button') }}
                        </a>
                    @endif
                    @if($canChangeIcons)
                        <button type="button" class="btn btn-outline-premium btn-sm" id="openIconBulkModalBtn">
                            <i class="fas fa-icons me-1"></i>{{ __('app.user.devices.change_icon') }}
                        </button>
                    @endif
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="deviceSearch" placeholder="{{ __('app.user.devices.search_placeholder') }}">
                    </div>
                </div>
            </div>

            @if($canChangeIcons)
                <p class="small text-muted mb-2" id="iconCheckboxHint">{{ __('app.user.devices.icon_checkbox_hint') }}</p>
                <div id="iconBulkBar" class="align-items-center flex-wrap gap-3 mb-3 p-3 rounded-3 border bg-light">
                    <strong id="iconSelectedCountLabel">{{ __('app.user.devices.icon_selected_count', ['count' => 0]) }}</strong>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="iconSelectAllBtn">{{ __('app.user.devices.icon_select_all') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="iconClearSelectionBtn">{{ __('app.user.devices.icon_clear_selection') }}</button>
                    <button type="button" class="btn btn-sm btn-primary" id="iconBulkChangeBtn">
                        <i class="fas fa-icons me-1"></i>{{ __('app.user.devices.change_icon') }}
                    </button>
                </div>
            @endif

            <div class="table-responsive devices-table-scroll">
                <table class="table table-hover mb-0" id="devicesTable">
                    <thead>
                    <tr>
                        @if($canChangeIcons)
                            <th style="width: 42px;">
                                <input type="checkbox" class="form-check-input device-icon-check" id="iconSelectAllHeader" title="{{ __('app.user.devices.icon_select_all') }}">
                            </th>
                        @endif
                        <th>{{ __('app.user.devices.column_device') }}</th>
                        <th>{{ __('app.user.devices.column_imei') }}</th>
                        <th>{{ __('app.user.devices.column_type') }}</th>
                        <th>{{ __('app.user.devices.column_live_status') }}</th>
                        <th>{{ __('app.user.devices.column_speed') }}</th>
                        <th>{{ __('app.user.devices.column_last_update') }}</th>
                        <th>{{ __('app.user.devices.column_subscription') }}</th>
                        <th>{{ __('app.user.devices.column_device_status') }}</th>
                        <th class="text-end">{{ __('app.common.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody id="deviceTableBody">
                    @forelse($devices as $d)
                        @php
                            $liveStatus = $dashboardService->resolveDeviceStatus($d, $alertDeviceIds);
                            $latest = $d->latestLocation;
                            $subStatus = $subscriptionService->statusLabel($d);
                            $accessCheck = $deviceAccessMap[$d->id] ?? ['allowed' => false, 'title' => '', 'message' => '', 'reason' => ''];
                            $canTrack = $accessCheck['allowed'];
                            $isSubscriptionLock = ($accessCheck['reason'] ?? '') === 'subscription_inactive';
                            $canViewVehicleDetails = $mapIconAuth->canViewVehicleDetails(auth()->user(), $d);
                            $canEditThisIcon = $canChangeIcons && $mapIconAuth->canEditAppearance(auth()->user(), $d);
                            $lockTitle = $canTrack
                                ? ''
                                : ($isSubscriptionLock
                                    ? __('app.user.devices.subscribe_for_map_message')
                                    : ($accessCheck['title'] . ' — ' . $accessCheck['message']));
                        @endphp
                        <tr class="device-row" data-device-id="{{ $d->id }}" data-imei="{{ $d->imei }}"
                            data-search="{{ strtolower(($d->vehicle_name ?? '') . ' ' . ($d->vehicle_number ?? '') . ' ' . $d->name . ' ' . $d->imei . ' ' . ($d->vehicle_model ?? '')) }}">
                            @if($canChangeIcons)
                                <td>
                                    @if($canEditThisIcon)
                                        <input type="checkbox"
                                               class="form-check-input device-icon-check js-icon-device-check"
                                               value="{{ $d->id }}"
                                               data-label="{{ $d->listPrimaryLabel() }}"
                                               aria-label="{{ __('app.user.devices.change_icon') }}">
                                    @endif
                                </td>
                            @endif
                            <td data-field="vehicle-identity">
                                <div class="d-flex align-items-center">
                                    <div class="device-icon me-3">
                                        @include('partials.device-map-icon-thumb', ['device' => $d, 'size' => 36])
                                    </div>
                                    <div class="vehicle-list-identity">
                                        <span class="vehicle-list-name">{{ $d->listPrimaryLabel() }}</span>
                                        @if($secondary = $d->listSecondaryLabel())
                                            <span class="vehicle-list-plate"><x-admin.ltr>{{ $secondary }}</x-admin.ltr></span>
                                        @else
                                            <span class="vehicle-list-plate d-none"></span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="bg-light p-2 rounded admin-ltr" dir="ltr">{{ $d->imei }}</code>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $d->deviceTypeLabel() }}</span>
                            </td>
                            <td data-field="live-status">
                                <div class="d-flex align-items-center">
                                    <div class="status-indicator me-2 {{ $liveStatus['dot'] }}"></div>
                                    <span class="badge {{ $liveStatus['class'] }}">{{ $liveStatus['label'] }}</span>
                                </div>
                            </td>
                            <td data-field="speed">
                                @if($latest)
                                    {{ number_format((float) ($latest->speed ?? 0), 0) }} {{ __('app.map.kmh_unit') }}
                                @else
                                    <span class="text-muted">{{ __('app.map.dash') }}</span>
                                @endif
                            </td>
                            <td data-field="last-update">
                                <small class="text-muted d-block">
                                    {{ $latest?->recorded_at?->diffForHumans() ?? __('app.user.devices.no_data_yet') }}
                                </small>
                            </td>
                            <td>
                                <span class="badge {{ $subStatus['class'] }}">{{ $subStatus['label'] }}</span>
                                @if($isSubscriptionLock)
                                    <div class="small text-warning mt-1">{{ __('app.user.devices.subscribe_for_map_message') }}</div>
                                @endif
                            </td>
                            <td>
                                @include('partials.device-status-badge', ['device' => $d])
                            </td>
                            <td class="text-end">
                                <div class="device-actions">
                                    @if($canViewVehicleDetails)
                                        <button type="button"
                                                class="btn btn-outline-premium btn-sm btn-edit-vehicle"
                                                data-device-id="{{ $d->id }}"
                                                data-vehicle-name="{{ $d->vehicle_name ?? '' }}"
                                                data-vehicle-number="{{ $d->vehicle_number ?? '' }}"
                                                data-odometer-base-km="{{ $d->odometerBaselineKm() ?? '' }}"
                                                data-odometer-display-km="{{ $d->odometerDisplayKm() ?? '' }}"
                                                data-fuel-rate="{{ $d->fuelConsumptionLPer100km() ?? '' }}"
                                                data-fuel-unit="{{ $d->fuelEfficiencyUnit() }}"
                                                data-fuel-tank="{{ $d->fuelTankCapacityL() ?? '' }}"
                                                data-fuel-sensor-unit="{{ $d->fuelSensorUnit() }}"
                                                data-update-url="{{ route('user.devices.vehicle-label', $d) }}"
                                                title="{{ __('app.user.devices.edit_vehicle') }}">
                                            <i class="fas fa-pen"></i><span class="btn-label">{{ __('app.user.devices.edit_vehicle') }}</span>
                                        </button>
                                    @endif
                                    @if($canTrack)
                                        <a href="{{ $d->launchMapRoute() }}" class="btn btn-premium btn-sm" title="{{ __('app.user.devices.track') }}">
                                            <i class="fas fa-map-marked-alt"></i><span class="btn-label">{{ __('app.user.devices.track') }}</span>
                                        </a>
                                    @else
                                        <button type="button"
                                                class="btn {{ $isSubscriptionLock ? 'btn-outline-warning' : 'btn-secondary' }} btn-sm"
                                                disabled
                                                title="{{ $lockTitle }}">
                                            <i class="fas fa-{{ $isSubscriptionLock ? 'credit-card' : 'lock' }}"></i>
                                            <span class="btn-label">{{ $isSubscriptionLock ? __('app.user.devices.subscribe_to_track') : __('app.user.devices.map_locked') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="noDevicesRow">
                            <td colspan="{{ $canChangeIcons ? 10 : 9 }}">
                                <div class="text-center py-5">
                                    <div class="mb-3">
                                        <i class="fas fa-satellite fa-4x text-muted opacity-25"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">{{ __('app.user.devices.no_devices') }}</h5>
                                    <p class="text-muted mb-0">{{ __('app.user.devices.no_devices_desc') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    @if($canChangeIcons && $iconDevice)
        <div class="modal fade" id="changeIconModal" tabindex="-1" aria-labelledby="changeIconModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="changeIconModalLabel">{{ __('app.user.devices.change_icon_modal_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.map.close_panel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">{{ __('app.user.devices.change_icon_modal_hint') }}</p>
                        <div class="alert alert-danger d-none" id="changeIconError" role="alert"></div>
                        <div class="alert alert-success d-none" id="changeIconSuccess" role="alert"></div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <strong id="changeIconSelectedLabel">{{ __('app.user.devices.icon_selected_count', ['count' => 0]) }}</strong>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="changeIconSelectAllBtn">{{ __('app.user.devices.icon_select_all') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="changeIconClearBtn">{{ __('app.user.devices.icon_clear_selection') }}</button>
                                </div>
                            </div>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                <input type="search"
                                       class="form-control"
                                       id="changeIconVehicleSearch"
                                       placeholder="{{ __('app.user.devices.icon_search_placeholder') }}"
                                       autocomplete="off">
                            </div>
                            <div class="icon-vehicle-picker" id="changeIconVehicleList">
                                @foreach($devices as $pickerDevice)
                                    @if($mapIconAuth->canEditAppearance($devicesUser, $pickerDevice))
                                        @php
                                            $pickerSearch = strtolower(trim(implode(' ', array_filter([
                                                $pickerDevice->listPrimaryLabel(),
                                                $pickerDevice->listSecondaryLabel(),
                                                $pickerDevice->imei,
                                                $pickerDevice->name,
                                                $pickerDevice->vehicle_model,
                                            ]))));
                                        @endphp
                                        <div class="icon-vehicle-picker__item" data-search="{{ $pickerSearch }}">
                                            <input type="checkbox"
                                                   class="form-check-input js-modal-icon-device"
                                                   id="modalIconDevice{{ $pickerDevice->id }}"
                                                   value="{{ $pickerDevice->id }}">
                                            @include('partials.device-map-icon-thumb', [
                                                'device' => $pickerDevice,
                                                'size' => 28,
                                                'class' => 'icon-vehicle-picker__thumb',
                                            ])
                                            <label for="modalIconDevice{{ $pickerDevice->id }}">
                                                <span>{{ $pickerDevice->listPrimaryLabel() }}</span>
                                                @if($secondary = $pickerDevice->listSecondaryLabel())
                                                    <span class="icon-vehicle-picker__meta"><x-admin.ltr>{{ $secondary }}</x-admin.ltr></span>
                                                @elseif($pickerDevice->imei)
                                                    <span class="icon-vehicle-picker__meta"><x-admin.ltr>{{ $pickerDevice->imei }}</x-admin.ltr></span>
                                                @endif
                                            </label>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <p class="small text-muted mb-0 mt-1 d-none" id="changeIconSearchEmpty">{{ __('app.user.devices.icon_search_empty') }}</p>
                        </div>

                        @include('user.partials.map-marker-appearance-form', [
                            'device' => $iconDevice,
                            'formId' => 'devicesMapMarkerAppearanceForm',
                            'showHead' => false,
                            'showBack' => false,
                            'mapAppearanceSaveUrl' => route('user.devices.map-appearance-bulk'),
                            'mapCustomIconUploadUrl' => route('user.devices.map-custom-icon-bulk'),
                            'mapCustomIconDeleteUrl' => '',
                        ])
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editVehicleForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="editVehicleModalLabel">{{ __('app.user.devices.edit_vehicle_modal_title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.map.close_panel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">{{ __('app.user.devices.edit_vehicle_modal_hint') }}</p>
                        <div class="alert alert-danger d-none" id="editVehicleError" role="alert"></div>
                        <div class="mb-3">
                            <label for="editVehicleName" class="form-label">{{ __('app.forms.vehicle_name') }}</label>
                            <input type="text" class="form-control" id="editVehicleName" name="vehicle_name" maxlength="120"
                                   placeholder="{{ __('app.forms.vehicle_name_placeholder') }}">
                            <div class="form-text">{{ __('app.forms.vehicle_name_hint') }}</div>
                        </div>
                        <div class="mb-0">
                            <label for="editVehicleNumber" class="form-label">{{ __('app.forms.vehicle_number') }}</label>
                            <input type="text" class="form-control admin-ltr" dir="ltr" id="editVehicleNumber" name="vehicle_number" maxlength="40"
                                   placeholder="{{ __('app.forms.vehicle_number_placeholder') }}">
                            <div class="form-text">{{ __('app.forms.vehicle_number_hint') }}</div>
                        </div>
                        <div class="mb-0 mt-3">
                            <label for="editVehicleOdometer" class="form-label">{{ __('app.odometer.current_reading') }}</label>
                            <input type="number" class="form-control admin-ltr" dir="ltr" id="editVehicleOdometer" name="odometer_base_km"
                                   step="0.1" min="0" placeholder="{{ __('app.odometer.placeholder') }}">
                            <div class="form-text">{{ __('app.odometer.hint') }}</div>
                            <div class="form-text d-none" id="editVehicleOdometerLive"></div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label for="editVehicleFuelRate" class="form-label">{{ __('app.fuel.consumption_rate') }}</label>
                            <input type="number" class="form-control admin-ltr" dir="ltr" id="editVehicleFuelRate" name="fuel_consumption_l_per_100km"
                                   step="0.1" min="0.1" max="100" placeholder="{{ __('app.fuel.consumption_placeholder') }}">
                            <div class="form-text">{{ __('app.fuel.consumption_hint') }}</div>
                        </div>
                        <div class="mb-3">
                            <label for="editVehicleFuelUnit" class="form-label">{{ __('app.fuel.efficiency_unit') }}</label>
                            <select class="form-select" id="editVehicleFuelUnit" name="fuel_efficiency_unit">
                                <option value="l_per_100km">{{ __('app.fuel.unit_l_100') }}</option>
                                <option value="km_per_l">{{ __('app.fuel.unit_km_l') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editVehicleFuelTank" class="form-label">{{ __('app.fuel.tank_capacity') }}</label>
                            <input type="number" class="form-control admin-ltr" dir="ltr" id="editVehicleFuelTank" name="fuel_tank_capacity_l"
                                   step="0.1" min="1" max="2000" placeholder="{{ __('app.fuel.tank_placeholder') }}">
                            <div class="form-text">{{ __('app.fuel.tank_hint') }}</div>
                        </div>
                        <div class="mb-0">
                            <label for="editVehicleFuelSensorUnit" class="form-label">{{ __('app.fuel.sensor_unit') }}</label>
                            <select class="form-select" id="editVehicleFuelSensorUnit" name="fuel_sensor_unit">
                                <option value="liters">{{ __('app.fuel.sensor_liters') }}</option>
                                <option value="percent">{{ __('app.fuel.sensor_percent') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-premium" data-bs-dismiss="modal">{{ __('app.common.cancel') }}</button>
                        <button type="submit" class="btn btn-premium" id="editVehicleSaveBtn">
                            <i class="fas fa-save me-1"></i> {{ __('app.common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.USER_DEVICES_LIVE = {
            pollUrl: @json(route('user.devices.live-json')),
            pollMs: 10000,
            dash: @json(__('app.map.dash')),
            noData: @json(__('app.user.devices.no_data_yet')),
            kmh: @json(__('app.map.kmh_unit')),
        };
        window.USER_DEVICES_EDIT = {
            saving: @json(__('app.user.devices.saving')),
            save: @json(__('app.common.save')),
            saved: @json(__('app.user.devices.vehicle_label_saved')),
            failed: @json(__('app.user.devices.vehicle_label_save_failed')),
            liveOdometer: @json(__('app.odometer.live_reading')),
        };
        window.USER_DEVICES_ICON = {
            enabled: @json($canChangeIcons),
            saveUrl: @json(route('user.devices.map-appearance-bulk')),
            uploadUrl: @json(route('user.devices.map-custom-icon-bulk')),
            selectVehicles: @json(__('app.user.devices.icon_select_vehicles')),
            selectedCount: @json(__('app.user.devices.icon_selected_count')),
            saved: @json(__('app.map.marker_appearance_saved')),
            failed: @json(__('app.map.marker_appearance_save_failed')),
            uploaded: @json(__('app.map.custom_icon_uploaded')),
            resized: @json(__('app.map.custom_icon_resized_notice')),
            liveScale: @json(__('app.map.map_live_scale')),
            noIcons: @json(__('app.map.no_icon_found')),
            sizes: {
                @foreach(\App\Support\VehicleIcons\VehicleIconLibrary::sizeScales() as $key => $scale)
                '{{ $key }}': @json(__('app.map.marker_size_'.$key)),
                @endforeach
            },
        };
    </script>
    <script src="{{ protected_js('user-devices-live.js') }}"></script>
    @if($canChangeIcons)
        <script src="{{ protected_js('builtin-map-icons.js') }}"></script>
        <script src="{{ protected_js('vehicle-marker.js') }}"></script>
        <script src="{{ protected_js('map-marker-appearance.js') }}"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deviceSearch = document.getElementById('deviceSearch');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (deviceSearch) {
                const runFilter = function () {
                    const query = deviceSearch.value.trim().toLowerCase();
                    document.querySelectorAll('.device-row').forEach((row) => {
                        const haystack = row.getAttribute('data-search') || '';
                        row.style.display = !query || haystack.includes(query) ? '' : 'none';
                    });
                };
                deviceSearch.addEventListener('input', runFilter);
                const params = new URLSearchParams(window.location.search);
                const q = params.get('q');
                if (q) {
                    deviceSearch.value = q;
                    runFilter();
                }
                const globalSearch = document.getElementById('udGlobalSearch');
                if (globalSearch && globalSearch.value.trim()) {
                    deviceSearch.value = globalSearch.value;
                    runFilter();
                }
            }

            const editModalEl = document.getElementById('editVehicleModal');
            const editForm = document.getElementById('editVehicleForm');
            if (editModalEl && editForm && typeof bootstrap !== 'undefined') {
                const editModal = new bootstrap.Modal(editModalEl);
                const nameInput = document.getElementById('editVehicleName');
                const numberInput = document.getElementById('editVehicleNumber');
                const odometerInput = document.getElementById('editVehicleOdometer');
                const odometerLive = document.getElementById('editVehicleOdometerLive');
                const fuelRateInput = document.getElementById('editVehicleFuelRate');
                const fuelUnitInput = document.getElementById('editVehicleFuelUnit');
                const fuelTankInput = document.getElementById('editVehicleFuelTank');
                const fuelSensorUnitInput = document.getElementById('editVehicleFuelSensorUnit');
                const errorBox = document.getElementById('editVehicleError');
                const saveBtn = document.getElementById('editVehicleSaveBtn');
                const i18n = window.USER_DEVICES_EDIT || {};
                let activeRow = null;
                let updateUrl = '';

                function setOdometerLive(km) {
                    if (!odometerLive) return;
                    if (km === undefined || km === null || km === '') {
                        odometerLive.classList.add('d-none');
                        odometerLive.textContent = '';
                        return;
                    }
                    const n = Number(km);
                    odometerLive.textContent = (i18n.liveOdometer || 'Live odometer') + ': ' +
                        (Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 1 }) : km) + ' km';
                    odometerLive.classList.remove('d-none');
                }

                document.querySelectorAll('.btn-edit-vehicle').forEach((btn) => {
                    btn.addEventListener('click', function () {
                        activeRow = btn.closest('.device-row');
                        updateUrl = btn.getAttribute('data-update-url') || '';
                        nameInput.value = btn.getAttribute('data-vehicle-name') || '';
                        numberInput.value = btn.getAttribute('data-vehicle-number') || '';
                        if (odometerInput) {
                            odometerInput.value = btn.getAttribute('data-odometer-base-km') || '';
                        }
                        if (fuelRateInput) fuelRateInput.value = btn.getAttribute('data-fuel-rate') || '';
                        if (fuelUnitInput) fuelUnitInput.value = btn.getAttribute('data-fuel-unit') || 'l_per_100km';
                        if (fuelTankInput) fuelTankInput.value = btn.getAttribute('data-fuel-tank') || '';
                        if (fuelSensorUnitInput) fuelSensorUnitInput.value = btn.getAttribute('data-fuel-sensor-unit') || 'liters';
                        setOdometerLive(btn.getAttribute('data-odometer-display-km'));
                        errorBox.classList.add('d-none');
                        errorBox.textContent = '';
                        editModal.show();
                    });
                });

                editForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    if (!updateUrl) {
                        return;
                    }

                    errorBox.classList.add('d-none');
                    errorBox.textContent = '';
                    saveBtn.disabled = true;
                    const originalHtml = saveBtn.innerHTML;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> ' + (i18n.saving || 'Saving...');

                    try {
                        const response = await fetch(updateUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                vehicle_name: nameInput.value.trim(),
                                vehicle_number: numberInput.value.trim(),
                                odometer_base_km: odometerInput ? odometerInput.value.trim() : '',
                                fuel_consumption_l_per_100km: fuelRateInput ? fuelRateInput.value.trim() : '',
                                fuel_efficiency_unit: fuelUnitInput ? fuelUnitInput.value : 'l_per_100km',
                                fuel_tank_capacity_l: fuelTankInput ? fuelTankInput.value.trim() : '',
                                fuel_sensor_unit: fuelSensorUnitInput ? fuelSensorUnitInput.value : 'liters',
                            }),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.success) {
                            const message = payload.message || i18n.failed || 'Could not save vehicle details.';
                            errorBox.textContent = message;
                            errorBox.classList.remove('d-none');
                            return;
                        }

                        const labels = payload.labels || {};
                        const deviceId = activeRow?.getAttribute('data-device-id');

                        if (activeRow) {
                            const nameEl = activeRow.querySelector('.vehicle-list-name');
                            const plateEl = activeRow.querySelector('.vehicle-list-plate');
                            if (nameEl) {
                                nameEl.textContent = labels.primary_label || nameInput.value.trim();
                            }
                            if (plateEl) {
                                const plate = labels.secondary_label || numberInput.value.trim();
                                if (plate) {
                                    plateEl.textContent = plate;
                                    plateEl.classList.remove('d-none');
                                } else {
                                    plateEl.textContent = '';
                                    plateEl.classList.add('d-none');
                                }
                            }

                            const imei = activeRow.getAttribute('data-imei') || '';
                            activeRow.setAttribute(
                                'data-search',
                                [
                                    labels.vehicle_name || nameInput.value.trim(),
                                    labels.vehicle_number || numberInput.value.trim(),
                                    imei,
                                ].join(' ').toLowerCase()
                            );
                            activeRow.classList.add('row-updated');
                            window.setTimeout(() => activeRow.classList.remove('row-updated'), 1200);
                        }

                        document.querySelectorAll('.btn-edit-vehicle').forEach((btn) => {
                            if (deviceId && btn.getAttribute('data-device-id') === deviceId) {
                                btn.setAttribute('data-vehicle-name', labels.vehicle_name || '');
                                btn.setAttribute('data-vehicle-number', labels.vehicle_number || '');
                                if (labels.odometer_base_km !== undefined && labels.odometer_base_km !== null) {
                                    btn.setAttribute('data-odometer-base-km', labels.odometer_base_km);
                                }
                                if (labels.odometer_display_km !== undefined && labels.odometer_display_km !== null) {
                                    btn.setAttribute('data-odometer-display-km', labels.odometer_display_km);
                                }
                                if (labels.fuel_consumption_l_per_100km !== undefined) {
                                    btn.setAttribute('data-fuel-rate', labels.fuel_consumption_l_per_100km ?? '');
                                }
                                if (labels.fuel_efficiency_unit) {
                                    btn.setAttribute('data-fuel-unit', labels.fuel_efficiency_unit);
                                }
                                if (labels.fuel_tank_capacity_l !== undefined) {
                                    btn.setAttribute('data-fuel-tank', labels.fuel_tank_capacity_l ?? '');
                                }
                                if (labels.fuel_sensor_unit) {
                                    btn.setAttribute('data-fuel-sensor-unit', labels.fuel_sensor_unit);
                                }
                            }
                        });

                        setOdometerLive(labels.odometer_display_km);
                        editModal.hide();
                    } catch (error) {
                        errorBox.textContent = i18n.failed || 'Could not save vehicle details.';
                        errorBox.classList.remove('d-none');
                    } finally {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalHtml;
                    }
                });
            }

            const iconCfg = window.USER_DEVICES_ICON || {};
            const iconModalEl = document.getElementById('changeIconModal');
            const iconForm = document.getElementById('devicesMapMarkerAppearanceForm');
            if (!iconCfg.enabled || !iconModalEl || !iconForm || typeof bootstrap === 'undefined' || !window.MapMarkerAppearance) {
                return;
            }

            const iconModal = new bootstrap.Modal(iconModalEl);
            const errorBox = document.getElementById('changeIconError');
            const successBox = document.getElementById('changeIconSuccess');
            const selectedLabel = document.getElementById('changeIconSelectedLabel');
            const bulkBar = document.getElementById('iconBulkBar');
            const bulkCountLabel = document.getElementById('iconSelectedCountLabel');
            const tableChecks = () => Array.from(document.querySelectorAll('.js-icon-device-check'));
            const modalChecks = () => Array.from(document.querySelectorAll('.js-modal-icon-device'));

            const formatCount = (count) => (iconCfg.selectedCount || ':count vehicle(s) selected').replace(':count', String(count));

            const selectedIds = () => modalChecks().filter((el) => el.checked).map((el) => parseInt(el.value, 10)).filter(Boolean);

            const syncSelectedCount = () => {
                const count = selectedIds().length;
                if (selectedLabel) selectedLabel.textContent = formatCount(count);
                if (bulkCountLabel) bulkCountLabel.textContent = formatCount(tableChecks().filter((el) => el.checked).length);
                if (bulkBar) {
                    const tableCount = tableChecks().filter((el) => el.checked).length;
                    bulkBar.classList.toggle('is-visible', tableCount > 0);
                }
            };

            const setModalSelection = (ids) => {
                const idSet = new Set((ids || []).map((id) => String(id)));
                modalChecks().forEach((el) => {
                    el.checked = idSet.has(String(el.value));
                });
                syncSelectedCount();
            };

            const setTableSelection = (ids) => {
                const idSet = new Set((ids || []).map((id) => String(id)));
                tableChecks().forEach((el) => {
                    el.checked = idSet.has(String(el.value));
                });
                syncSelectedCount();
            };

            const syncTableFromModal = () => {
                setTableSelection(modalChecks().filter((el) => el.checked).map((el) => el.value));
            };

            const vehicleSearch = document.getElementById('changeIconVehicleSearch');
            const searchEmpty = document.getElementById('changeIconSearchEmpty');
            const vehicleItems = () => Array.from(document.querySelectorAll('#changeIconVehicleList .icon-vehicle-picker__item'));

            const filterVehicleList = () => {
                const q = (vehicleSearch?.value || '').trim().toLowerCase();
                let visible = 0;
                vehicleItems().forEach((item) => {
                    const hay = item.getAttribute('data-search') || item.textContent || '';
                    const show = !q || hay.includes(q);
                    item.classList.toggle('d-none', !show);
                    if (show) visible += 1;
                });
                if (searchEmpty) searchEmpty.classList.toggle('d-none', visible > 0 || !q);
            };

            const openIconModal = (ids) => {
                const selected = (ids || []).map((id) => String(id)).filter(Boolean);
                if (errorBox) {
                    errorBox.classList.add('d-none');
                    errorBox.textContent = '';
                }
                if (successBox) {
                    successBox.classList.add('d-none');
                    successBox.textContent = '';
                }
                const statusEl = iconForm.querySelector('[data-map-save-status]');
                if (statusEl) statusEl.textContent = '';
                // Bulk modal: always start from the default map icon (not a prior custom/AI upload preview).
                iconForm.dataset.customActive = '0';
                delete iconForm.dataset.customPreviewUrl;
                delete iconForm.dataset.customPreviewBlob;
                iconForm.querySelector('[data-map-revert-custom]')?.setAttribute('hidden', 'hidden');
                const typeEl = iconForm.querySelector('[data-map-vehicle-type]');
                const pathEl = iconForm.querySelector('[data-map-builtin-icon-path]');
                if (typeEl) typeEl.value = 'car';
                if (pathEl) pathEl.value = 'Vehicles/car.svg';
                window.MapMarkerAppearance?.renderPreview?.(iconForm, {});
                if (vehicleSearch) vehicleSearch.value = '';
                filterVehicleList();
                // Keep table + modal in sync: checked list rows become selected in Change icon.
                setTableSelection(selected);
                setModalSelection(selected);
                iconModal.show();
                setTimeout(() => {
                    const firstSelected = modalChecks().find((el) => el.checked);
                    firstSelected?.closest('.icon-vehicle-picker__item')?.scrollIntoView({ block: 'nearest' });
                    if (!selected.length) vehicleSearch?.focus();
                }, 200);
            };

            tableChecks().forEach((el) => el.addEventListener('change', syncSelectedCount));
            modalChecks().forEach((el) => el.addEventListener('change', () => {
                syncTableFromModal();
                syncSelectedCount();
            }));
            vehicleSearch?.addEventListener('input', filterVehicleList);

            document.getElementById('iconSelectAllHeader')?.addEventListener('change', function () {
                const checked = this.checked;
                tableChecks().forEach((el) => {
                    const row = el.closest('.device-row');
                    if (row && row.style.display === 'none') return;
                    el.checked = checked;
                });
                syncSelectedCount();
            });

            document.getElementById('iconSelectAllBtn')?.addEventListener('click', () => {
                tableChecks().forEach((el) => {
                    const row = el.closest('.device-row');
                    if (row && row.style.display === 'none') return;
                    el.checked = true;
                });
                syncSelectedCount();
            });

            document.getElementById('iconClearSelectionBtn')?.addEventListener('click', () => {
                setTableSelection([]);
                const header = document.getElementById('iconSelectAllHeader');
                if (header) header.checked = false;
            });

            document.getElementById('changeIconSelectAllBtn')?.addEventListener('click', () => {
                modalChecks().forEach((el) => {
                    const item = el.closest('.icon-vehicle-picker__item');
                    if (item && item.classList.contains('d-none')) return;
                    el.checked = true;
                });
                syncSelectedCount();
            });

            document.getElementById('changeIconClearBtn')?.addEventListener('click', () => {
                setModalSelection([]);
            });

            const openFromTableSelection = () => {
                const ids = tableChecks().filter((el) => el.checked).map((el) => String(el.value));
                openIconModal(ids);
            };
            document.getElementById('openIconBulkModalBtn')?.addEventListener('click', openFromTableSelection);
            document.getElementById('iconBulkChangeBtn')?.addEventListener('click', openFromTableSelection);

            window.MapMarkerAppearance.bindForm({
                form: iconForm,
                saveUrl: iconCfg.saveUrl,
                uploadUrl: iconCfg.uploadUrl,
                csrf,
                getDeviceIds: selectedIds,
                i18n: {
                    saved: iconCfg.saved,
                    failed: iconCfg.failed,
                    uploaded: iconCfg.uploaded,
                    resized: iconCfg.resized,
                    liveScale: iconCfg.liveScale,
                    sizes: iconCfg.sizes,
                    selectVehicles: iconCfg.selectVehicles,
                    noIcons: iconCfg.noIcons || 'No icon found',
                },
                onSaved: (_appearance, data) => {
                    if (successBox) {
                        successBox.textContent = data?.message || iconCfg.saved || 'Saved';
                        successBox.classList.remove('d-none');
                    }
                    if (errorBox) {
                        errorBox.classList.add('d-none');
                        errorBox.textContent = '';
                    }
                },
                onError: (message) => {
                    if (errorBox) {
                        errorBox.textContent = message || iconCfg.failed || 'Failed';
                        errorBox.classList.remove('d-none');
                    }
                    if (successBox) {
                        successBox.classList.add('d-none');
                        successBox.textContent = '';
                    }
                },
            });

            syncSelectedCount();
        });
    </script>
@endpush
