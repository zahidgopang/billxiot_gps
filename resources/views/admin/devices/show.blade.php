@extends('admin.layouts.app')

@section('title', $device->listPrimaryLabel())
@section('page-title', __('app.admin.devices.device_details'))

@section('content')
    @php $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin'); @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-1">{{ $device->listPrimaryLabel() }}</h5>
            @if($plate = $device->listSecondaryLabel())
                <p class="text-muted small mb-0"><x-admin.ltr>{{ $plate }}</x-admin.ltr></p>
            @endif
        </div>
        <div class="d-flex gap-1 flex-wrap">
            <a href="{{ route($panel . '.devices.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ __('app.admin.devices.back_to_list') }}
            </a>
            @if($canManage)
                <a href="{{ route($panel . '.devices.edit', $device) }}" class="btn btn-sm btn-primary">{{ __('app.common.edit') }}</a>
            @endif
            @if(Gate::allows('permission', 'maps.view'))
                <a href="{{ route($panel . '.locations.launch-map', $device) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-map-marked-alt me-1" aria-hidden="true"></i>{{ __('app.admin.nav.track_devices') }}
                </a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent">
                    <strong>{{ __('app.forms.device_information') }}</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-muted">{{ __('app.admin.devices.imei') }}</dt>
                        <dd class="col-sm-7 admin-ltr" dir="ltr"><code>{{ $device->imei }}</code></dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.device_label') }}</dt>
                        <dd class="col-sm-7">{{ $device->name ?: '—' }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.device_type') }}</dt>
                        <dd class="col-sm-7">{{ $device->deviceTypeLabel() }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.common.status') }}</dt>
                        <dd class="col-sm-7">
                            @php
                                $statusClass = match ($device->status) {
                                    'active' => 'success',
                                    'blocked' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $statusClass }}">
                                {{ match ($device->status) {
                                    'active' => __('app.common.active'),
                                    'blocked' => __('app.common.blocked'),
                                    default => __('app.common.inactive'),
                                } }}
                            </span>
                        </dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.admin.devices.user') }}</dt>
                        <dd class="col-sm-7">
                            @if($device->user)
                                <a href="{{ route($panel . '.users.show', $device->user) }}">{{ $device->user->name }}</a>
                            @else
                                —
                            @endif
                        </dd>

                        @if($panel === 'admin' && $client)
                            <dt class="col-sm-5 text-muted">{{ __('app.forms.client_company') }}</dt>
                            <dd class="col-sm-7">{{ $client->name }}</dd>
                        @endif

                        <dt class="col-sm-5 text-muted">{{ __('app.admin.devices.last_known') }}</dt>
                        <dd class="col-sm-7"><x-admin.ltr>{{ optional($device->latestLocation?->recorded_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.ltr></dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <strong>{{ __('app.forms.sim_type') }}</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-muted">{{ __('app.forms.sim_type') }}</dt>
                        <dd class="col-sm-7">{{ $device->simTypeLabel() }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.sim_number') }}</dt>
                        <dd class="col-sm-7 admin-ltr" dir="ltr">{{ $device->sim_number ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent">
                    <strong>{{ __('app.forms.vehicle_information') }}</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-muted">{{ __('app.forms.vehicle_name') }}</dt>
                        <dd class="col-sm-7">{{ $device->vehicle_name ?: '—' }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.vehicle_number') }}</dt>
                        <dd class="col-sm-7 admin-ltr" dir="ltr">{{ $device->vehicle_number ?: '—' }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.vehicle_model') }}</dt>
                        <dd class="col-sm-7">{{ $device->vehicle_model ?: '—' }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.vehicle_type') }}</dt>
                        <dd class="col-sm-7">{{ $device->vehicleTypeLabel() }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.plate_type') }}</dt>
                        <dd class="col-sm-7">{{ $device->plateTypeLabel() }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <strong>{{ __('app.forms.driver_information') }}</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5 text-muted">{{ __('app.forms.driver_name') }}</dt>
                        <dd class="col-sm-7">{{ $device->driverDisplayName() ?? '—' }}</dd>

                        <dt class="col-sm-5 text-muted">{{ __('app.forms.driver_contact') }}</dt>
                        <dd class="col-sm-7 admin-ltr" dir="ltr">
                            @if($contact = $device->driverContactNumber())
                                @if($tel = $device->driverContactTel())
                                    <a href="tel:{{ $tel }}">{{ $contact }}</a>
                                @else
                                    {{ $contact }}
                                @endif
                            @else
                                —
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @if($device->subscription)
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-header bg-transparent">
                        <strong>{{ __('app.common.subscriptions') }}</strong>
                    </div>
                    <div class="card-body small">
                        <p class="mb-2">
                            <span class="text-muted">{{ __('app.common.plan') }}:</span>
                            <strong>{{ $device->subscription->plan }}</strong>
                        </p>
                        @can('permission', 'subscriptions.manage')
                            <a href="{{ route($panel . '.subscriptions.edit', $device->subscription) }}" class="btn btn-sm btn-outline-primary">
                                {{ __('app.common.edit') }} {{ __('app.common.subscriptions') }}
                            </a>
                        @endcan
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
