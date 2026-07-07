@extends('user.layout_user')

@section('title', __('app.user.devices.title') . ' - ' . __('app.brand'))

    @push('styles')
    <style>
        tr.device-row.row-updated { transition: background-color 0.4s ease; background-color: rgba(37, 99, 235, 0.06); }
        .stat-pulse { transition: transform 0.25s ease; transform: scale(1.06); }
    </style>
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
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="deviceSearch" placeholder="{{ __('app.user.devices.search_placeholder') }}">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="devicesTable">
                    <thead>
                    <tr>
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
                            $accessCheck = $deviceAccessMap[$d->id] ?? ['allowed' => false, 'title' => '', 'message' => ''];
                            $canTrack = $accessCheck['allowed'];
                            $lockTitle = $canTrack ? '' : ($accessCheck['title'] . ' — ' . $accessCheck['message']);
                        @endphp
                        <tr class="device-row" data-device-id="{{ $d->id }}" data-imei="{{ $d->imei }}"
                            data-search="{{ strtolower(($d->vehicle_name ?? '') . ' ' . ($d->vehicle_number ?? '') . ' ' . $d->name . ' ' . $d->imei . ' ' . ($d->vehicle_model ?? '')) }}">
                            <td data-field="vehicle-identity">
                                <div class="d-flex align-items-center">
                                    <div class="device-icon me-3">
                                        <i class="fas {{ $d->deviceTypeIconClass() }} fa-lg" style="color: var(--primary-blue);"></i>
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
                            </td>
                            <td>
                                @include('partials.device-status-badge', ['device' => $d])
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button"
                                            class="btn btn-outline-premium btn-sm btn-edit-vehicle"
                                            data-device-id="{{ $d->id }}"
                                            data-vehicle-name="{{ $d->vehicle_name ?? '' }}"
                                            data-vehicle-number="{{ $d->vehicle_number ?? '' }}"
                                            data-update-url="{{ route('user.devices.vehicle-label', $d) }}"
                                            title="{{ __('app.user.devices.edit_vehicle') }}">
                                        <i class="fas fa-pen me-1"></i> {{ __('app.user.devices.edit_vehicle') }}
                                    </button>
                                    @if($canTrack)
                                        <a href="{{ $d->launchMapRoute() }}" class="btn btn-premium btn-sm">
                                            <i class="fas fa-map-marked-alt me-1"></i> {{ __('app.user.devices.track') }}
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm" disabled title="{{ $lockTitle }}">
                                            <i class="fas fa-lock me-1"></i> {{ __('app.user.devices.map_locked') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="noDevicesRow">
                            <td colspan="9">
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
        };
    </script>
    <script src="{{ protected_js('user-devices-live.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deviceSearch = document.getElementById('deviceSearch');
            if (!deviceSearch) {
                return;
            }
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

            const editModalEl = document.getElementById('editVehicleModal');
            const editForm = document.getElementById('editVehicleForm');
            if (!editModalEl || !editForm || typeof bootstrap === 'undefined') {
                return;
            }

            const editModal = new bootstrap.Modal(editModalEl);
            const nameInput = document.getElementById('editVehicleName');
            const numberInput = document.getElementById('editVehicleNumber');
            const errorBox = document.getElementById('editVehicleError');
            const saveBtn = document.getElementById('editVehicleSaveBtn');
            const i18n = window.USER_DEVICES_EDIT || {};
            let activeRow = null;
            let updateUrl = '';

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            document.querySelectorAll('.btn-edit-vehicle').forEach((btn) => {
                btn.addEventListener('click', function () {
                    activeRow = btn.closest('.device-row');
                    updateUrl = btn.getAttribute('data-update-url') || '';
                    nameInput.value = btn.getAttribute('data-vehicle-name') || '';
                    numberInput.value = btn.getAttribute('data-vehicle-number') || '';
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
                        }
                    });

                    editModal.hide();
                } catch (error) {
                    errorBox.textContent = i18n.failed || 'Could not save vehicle details.';
                    errorBox.classList.remove('d-none');
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalHtml;
                }
            });
        });
    </script>
@endpush
