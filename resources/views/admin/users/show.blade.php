@extends('admin.layouts.app')
@section('title', $user->name)
@section('page-title', __('app.admin.users.user_details'))

@section('content')
    @php
        $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
        $roleEnum = \App\Enums\AppRole::tryFrom($user->role);
        $phone = trim(($user->country_code ?? '') . ' ' . ($user->phone ?? ''));
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-1">{{ $user->name }}</h5>
            <p class="text-muted small mb-0"><x-admin.ltr>{{ $user->email }}</x-admin.ltr></p>
        </div>
        <div class="d-flex gap-1 flex-wrap">
            <a href="{{ route($panel . '.users.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ __('app.admin.users.back_to_list') }}
            </a>
            @if($canManage)
                <a href="{{ route($panel . '.users.edit', $user) }}" class="btn btn-sm btn-primary">{{ __('app.common.edit') }}</a>
            @endif
            @if($user->isEndUserRole() && $devices->isNotEmpty() && Gate::allows('permission', 'maps.view'))
                <a href="{{ route($panel . '.users.fleet-map', $user) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-map-marked-alt me-1" aria-hidden="true"></i>{{ __('app.admin.nav.track_devices') }}
                </a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <strong>{{ __('app.admin.users.account_details') }}</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">{{ __('app.forms.name') }}</dt>
                        <dd class="col-sm-8 fw-semibold">{{ $user->name }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.forms.email') }}</dt>
                        <dd class="col-sm-8"><x-admin.ltr>{{ $user->email }}</x-admin.ltr></dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.forms.phone') }}</dt>
                        <dd class="col-sm-8 admin-ltr" dir="ltr">{{ $phone !== '' ? $phone : '—' }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.forms.role') }}</dt>
                        <dd class="col-sm-8">{{ $roleEnum?->label() ?? $user->role }}</dd>

                        <dt class="col-sm-4 text-muted">{{ __('app.common.status') }}</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-{{ $user->status === 'active' ? 'success' : 'secondary' }}">
                                {{ $user->status === 'active' ? __('app.common.active') : __('app.common.inactive') }}
                            </span>
                        </dd>

                        @if($panel === 'admin')
                            <dt class="col-sm-4 text-muted">{{ __('app.admin.users.client_link') }}</dt>
                            <dd class="col-sm-8">
                                @if($user->clients->isNotEmpty())
                                    {{ $user->clients->pluck('name')->join(', ') }}
                                @elseif($user->isEndUserRole())
                                    <span class="badge bg-warning text-dark">{{ __('app.admin.users.badge_not_linked') }}</span>
                                @else
                                    —
                                @endif
                            </dd>
                        @endif

                        <dt class="col-sm-4 text-muted">{{ __('app.forms.joined') }}</dt>
                        <dd class="col-sm-8"><x-admin.ltr>{{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}</x-admin.ltr></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <strong>{{ __('app.admin.users.assigned_devices') }}</strong>
                    <span class="badge bg-light text-dark border">{{ $devices->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @if($devices->isEmpty())
                        <p class="text-muted small mb-0 p-3">{{ __('app.admin.users.no_devices') }}</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                <tr>
                                    <th>{{ __('app.forms.vehicle_name') }}</th>
                                    <th>{{ __('app.admin.devices.imei') }}</th>
                                    <th>{{ __('app.admin.devices.last_known') }}</th>
                                    <th>{{ __('app.common.actions') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($devices as $d)
                                    <tr>
                                        <td>
                                            <strong>{{ $d->listPrimaryLabel() }}</strong>
                                            @if($plate = $d->listSecondaryLabel())
                                                <small class="d-block text-muted"><x-admin.ltr>{{ $plate }}</x-admin.ltr></small>
                                            @endif
                                        </td>
                                        <td><x-admin.ltr tag="code">{{ $d->imei }}</x-admin.ltr></td>
                                        <td><x-admin.ltr>{{ optional($d->latestLocation?->recorded_at)->diffForHumans() ?? '—' }}</x-admin.ltr></td>
                                        <td class="text-nowrap">
                                            <a href="{{ route($panel . '.devices.show', $d) }}" class="btn btn-sm btn-outline-secondary">{{ __('app.common.view') }}</a>
                                            @if(Gate::allows('permission', 'maps.view'))
                                                <a href="{{ route($panel . '.locations.launch-map', $d) }}" class="btn btn-sm btn-outline-primary" title="{{ __('app.admin.nav.track_devices') }}">
                                                    <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
