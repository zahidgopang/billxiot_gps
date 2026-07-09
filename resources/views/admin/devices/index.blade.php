@extends('admin.layouts.app')

@section('title', __('app.admin.devices.title'))
@section('page-title', __('app.admin.devices.title'))

@php
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $mapIconAuth = app(\App\Services\Tracking\DeviceMapIconAuthorization::class);
    $devicesUser = auth()->user();
    $canChangeIcons = $devicesUser && $mapIconAuth->hasAnyAppearancePermission($devicesUser);
    $iconDevices = $iconDevices ?? collect();
    $iconDevice = $canChangeIcons
        ? $iconDevices->first(fn ($d) => $mapIconAuth->canEditAppearance($devicesUser, $d))
        : null;
@endphp

@push('styles')
    <style>
        /* Skeleton rows */
        .skel-table { width:100%; border-collapse:collapse; }
        .skel-row { display: table-row; }
        .skel-cell {
            display: table-cell;
            padding: 18px;
            background: linear-gradient(90deg, #f3f3f3 25%, #ececec 37%, #f3f3f3 63%);
            background-size: 400% 100%;
            animation: sh 1.2s linear infinite;
            height: 42px;
            border-radius: 4px;
        }
        @keyframes sh { 0%{background-position:200% 0}100%{background-position:-200% 0} }

        /* Table small tweaks */
        .table-small td, .table-small th { padding: .75rem .8rem; }
        .badge-status { padding:.35rem .6rem; border-radius:999px; display:inline-block; font-size:.85rem; }
        .badge-active { background:#dff7e0; color:#2f7d3a; }
        .badge-inactive { background:#fdeedc; color:#8a4b1a; }
        .badge-blocked { background:#f9d6d6; color:#7a1a1a; }
        .device-status-toggle .form-check-input { cursor: pointer; width: 2.5em; height: 1.25em; }
        .device-status-toggle .form-check-input:disabled { cursor: not-allowed; }
        .device-icon-check { width: 1.1rem; height: 1.1rem; cursor: pointer; }
        #iconBulkBar { display: none; }
        #iconBulkBar.is-visible { display: flex; }
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
    </style>
    @if($canChangeIcons)
        <link rel="stylesheet" href="{{ asset('css/map-marker-appearance.css') }}?v={{ @filemtime(public_path('css/map-marker-appearance.css')) ?: 1 }}">
    @endif
@endpush

@section('content')
    <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">{{ __('app.admin.devices.title') }}</h5>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if($canChangeIcons && $iconDevice)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="openIconBulkModalBtn">
                        <i class="fas fa-icons me-1"></i>{{ __('app.user.devices.change_icon') }}
                    </button>
                @endif
                <a href="{{ route($panel . '.devices.create') }}" class="btn btn-sm btn-primary">{{ __('app.admin.devices.add') }}</a>
            </div>
        </div>

        <div class="mb-3">
            <form method="GET" class="admin-filter-bar d-flex flex-wrap gap-2 align-items-end">
                <div class="flex-grow-1" style="min-width: 12rem; max-width: 24rem;">
                    <label class="form-label small mb-1" for="devices-filter-q">{{ __('app.common.search') }}</label>
                    <input name="q" id="devices-filter-q" value="{{ request('q') }}" class="form-control form-control-sm admin-ltr" dir="ltr"
                           placeholder="{{ __('app.admin.devices.search_placeholder') }}">
                </div>
                <div class="admin-filter-actions">
                    <button class="btn btn-sm btn-primary" type="submit">{{ __('app.common.search') }}</button>
                    @if(request()->filled('q'))
                        <a href="{{ route($panel . '.devices.index') }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @if($canChangeIcons && $iconDevice)
            <div id="iconBulkBar" class="align-items-center flex-wrap gap-3 mb-3 p-3 rounded-3 border bg-light">
                <strong id="iconSelectedCountLabel">{{ __('app.user.devices.icon_selected_count', ['count' => 0]) }}</strong>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="iconSelectAllBtn">{{ __('app.user.devices.icon_select_all') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="iconClearSelectionBtn">{{ __('app.user.devices.icon_clear_selection') }}</button>
            </div>
        @endif

        <!-- SKELETON: shown while page rendering; hidden immediately after JS runs -->
        <div id="skeleton-area">
            <table class="skel-table">
                @for ($i=0;$i<6;$i++)
                    <div class="skel-row" style="display:table-row">
                        <div class="skel-cell" style="display:table-cell; width:20%"></div>
                        <div class="skel-cell" style="display:table-cell; width:25%"></div>
                        <div class="skel-cell" style="display:table-cell; width:25%"></div>
                        <div class="skel-cell" style="display:table-cell; width:15%"></div>
                        <div class="skel-cell" style="display:table-cell; width:15%"></div>
                    </div>
                @endfor
            </table>
        </div>

        <!-- REAL TABLE: initially hidden by JS until DOM ready -->
        <div id="real-area" style="display:none;">
            <table class="table table-hover table-small" id="devicesTable">
                <thead>
                <tr>
                    @if($canChangeIcons && $iconDevice)
                        <th style="width: 42px;">
                            <input type="checkbox" class="form-check-input device-icon-check" id="iconSelectAllHeader" title="{{ __('app.user.devices.icon_select_all') }}">
                        </th>
                    @endif
                    <th>{{ __('app.admin.devices.imei') }}</th>
                    <th>{{ __('app.forms.vehicle_name') }}</th>
                    <th>{{ __('app.admin.devices.type') }}</th>
                    <th>{{ __('app.forms.vehicle_type') }}</th>
                    <th>{{ __('app.admin.devices.user') }}</th>
                    <th>{{ __('app.common.status') }}</th>
                    <th>{{ __('app.admin.devices.last_known') }}</th>
                    <th>{{ __('app.common.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($devices as $d)
                    @php
                        $canEditThisIcon = $canChangeIcons && $mapIconAuth->canEditAppearance($devicesUser, $d);
                    @endphp
                    <tr class="device-row" data-device-id="{{ $d->id }}">
                        @if($canChangeIcons && $iconDevice)
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
                        <td><x-admin.ltr tag="code">{{ $d->imei }}</x-admin.ltr></td>
                        <td>
                            <strong>{{ $d->listPrimaryLabel() }}</strong>
                            @if($plate = $d->listSecondaryLabel())
                                <small class="d-block text-muted"><x-admin.ltr>{{ $plate }}</x-admin.ltr></small>
                            @endif
                            @if($d->vehicle_model)
                                <small class="d-block text-muted">{{ $d->vehicle_model }}</small>
                            @endif
                        </td>
                        <td><span class="badge bg-light text-dark border">{{ $d->deviceTypeLabel() }}</span></td>
                        <td>{{ $d->vehicleTypeLabel() }}</td>
                        <td>{{ $d->user?->name ?? '-' }}</td>
                        <td>
                            @include('partials.device-status-toggle', [
                                'device' => $d,
                                'toggleUrl' => route($panel . '.devices.toggle-status', $d),
                            ])
                        </td>
                        <td><x-admin.ltr>{{ optional($d->latestLocation?->recorded_at)->diffForHumans() ?? '-' }}</x-admin.ltr></td>
                        <td>
                            <a href="{{ route($panel . '.devices.show', $d) }}" class="btn btn-sm btn-outline-secondary">{{ __('app.common.view') }}</a>
                            <a href="{{ route($panel . '.devices.edit', $d) }}" class="btn btn-sm btn-outline-primary">{{ __('app.common.edit') }}</a>

                            @if($panel === 'admin')
                            <form action="{{ route('admin.devices.destroy', $d) }}" method="POST" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button type="button" class="btn btn-sm btn-danger btn-delete">{{ __('app.common.delete') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="mt-3">
                {{ $devices->links() }}
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
                                @foreach($iconDevices as $pickerDevice)
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
                            'mapAppearanceSaveUrl' => route($panel . '.devices.map-appearance-bulk'),
                            'mapCustomIconUploadUrl' => route($panel . '.devices.map-custom-icon-bulk'),
                            'mapCustomIconDeleteUrl' => '',
                        ])
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ protected_js('device-status-toggle.js') }}"></script>

    @if($canChangeIcons && $iconDevice)
        <script>
            window.ADMIN_DEVICES_ICON = {
                enabled: true,
                saveUrl: @json(route($panel . '.devices.map-appearance-bulk')),
                uploadUrl: @json(route($panel . '.devices.map-custom-icon-bulk')),
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
        <script src="{{ protected_js('builtin-map-icons.js') }}"></script>
        <script src="{{ protected_js('vehicle-marker.js') }}"></script>
        <script src="{{ protected_js('map-marker-appearance.js') }}"></script>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            // hide skeleton, show real
            const sk = document.getElementById('skeleton-area');
            const real = document.getElementById('real-area');
            if (sk) sk.style.display = 'none';
            if (real) real.style.display = '';

            // SweetAlert2 delete confirm
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function(e){
                    const form = this.closest('form');
                    Swal.fire({
                        title: window.APP_I18N?.areYouSure || 'Are you sure?',
                        text: window.APP_I18N?.cannotUndo || 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: window.APP_I18N?.yesDelete || 'Yes, delete it',
                        cancelButtonText: window.APP_I18N?.cancel || 'Cancel',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            const iconCfg = window.ADMIN_DEVICES_ICON || {};
            const iconModalEl = document.getElementById('changeIconModal');
            const iconForm = document.getElementById('devicesMapMarkerAppearanceForm');
            if (!iconCfg.enabled || !iconModalEl || !iconForm || typeof bootstrap === 'undefined' || !window.MapMarkerAppearance) {
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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
                if (vehicleSearch) vehicleSearch.value = '';
                filterVehicleList();
                setModalSelection(ids || []);
                iconModal.show();
                setTimeout(() => vehicleSearch?.focus(), 200);
            };

            tableChecks().forEach((el) => el.addEventListener('change', syncSelectedCount));
            modalChecks().forEach((el) => el.addEventListener('change', syncSelectedCount));
            vehicleSearch?.addEventListener('input', filterVehicleList);

            document.getElementById('iconSelectAllHeader')?.addEventListener('change', function () {
                const checked = this.checked;
                tableChecks().forEach((el) => { el.checked = checked; });
                syncSelectedCount();
            });

            document.getElementById('iconSelectAllBtn')?.addEventListener('click', () => {
                tableChecks().forEach((el) => { el.checked = true; });
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

            document.getElementById('openIconBulkModalBtn')?.addEventListener('click', () => {
                const ids = tableChecks().filter((el) => el.checked).map((el) => el.value);
                openIconModal(ids);
            });

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
