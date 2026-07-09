@php
    $panel = $panel ?? (request()->routeIs('user.*') ? 'user' : (request()->routeIs('client.*') ? 'client' : 'admin'));
    $isUserPanel = $panel === 'user';
    $layout = $isUserPanel ? 'user.layout_user' : 'admin.layouts.app';
    $canManage = auth()->user()?->canManageSubAccounts() ?? false;
    $phone = trim(implode(' ', array_filter([
        $subAccount->country_code ?? null,
        $subAccount->phone ?? null,
    ])));
@endphp

@extends($layout)

@section('title', __('app.sub_accounts.view'))
@if(! $isUserPanel)
@section('page-title', __('app.sub_accounts.view'))
@endif

@section('content')
    @if($isUserPanel)
        @include('sub-accounts._user-form-styles')

        <div class="ud-dashboard ud-fade-in sub-account-form">
            <header class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h1 class="ud-page-title">{{ __('app.sub_accounts.view') }}</h1>
                    <p class="ud-page-sub mb-0">{{ $subAccount->name }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('user.sub-accounts.index') }}" class="ud-btn ud-btn--secondary">
                        <i class="fas fa-arrow-left me-1"></i>{{ __('app.sub_accounts.back_to_list') }}
                    </a>
                    @if($canManage)
                        <a href="{{ route('user.sub-accounts.edit', $subAccount) }}" class="ud-btn ud-btn--primary">
                            <i class="fas fa-edit me-1"></i>{{ __('app.common.edit') }}
                        </a>
                    @endif
                </div>
            </header>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="mb-1">{{ __('app.sub_accounts.view') }}</h5>
                <p class="text-muted mb-0">{{ $subAccount->name }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route($panel . '.sub-accounts.index') }}" class="btn btn-light btn-sm">
                    {{ __('app.sub_accounts.back_to_list') }}
                </a>
                @if($canManage)
                    <a href="{{ route($panel . '.sub-accounts.edit', $subAccount) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>{{ __('app.common.edit') }}
                    </a>
                @endif
            </div>
        </div>
    @endif

        <section class="{{ $isUserPanel ? 'sa-form-section' : 'card p-3 mb-3' }}">
            <h2 class="{{ $isUserPanel ? 'sa-form-section__title' : 'h6 mb-3' }}">{{ __('app.forms.basic_information') }}</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">{{ __('app.common.name') }}</div>
                    <div class="fw-semibold">{{ $subAccount->name }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">{{ __('app.forms.email') }}</div>
                    <div class="fw-semibold admin-ltr" dir="ltr">{{ $subAccount->email }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">{{ __('app.common.status') }}</div>
                    <span class="badge {{ $subAccount->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                        {{ $subAccount->status === 'active' ? __('app.common.active') : __('app.common.inactive') }}
                    </span>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">{{ __('app.forms.phone') }}</div>
                    <div class="fw-semibold admin-ltr" dir="ltr">{{ $phone !== '' ? $phone : '—' }}</div>
                </div>
            </div>
        </section>

        <section class="{{ $isUserPanel ? 'sa-form-section' : 'card p-3 mb-3' }}">
            <h2 class="{{ $isUserPanel ? 'sa-form-section__title' : 'h6 mb-1' }}">{{ __('app.sub_accounts.vehicles_section') }}</h2>
            <p class="{{ $isUserPanel ? 'sa-form-section__hint' : 'text-muted small mb-3' }}">
                {{ __('app.sub_accounts.assigned_vehicles_count', ['count' => $devices->count()]) }}
            </p>

            @if($devices->isEmpty())
                <div class="alert alert-warning mb-0">{{ __('app.sub_accounts.no_vehicles') }}</div>
            @else
                <div class="row g-2">
                    @foreach($devices as $device)
                        <div class="col-md-6 col-lg-4">
                            <div class="border rounded p-2 h-100">
                                <strong>{{ $device->vehicle_name ?: $device->name }}</strong>
                                @if($device->vehicle_number)
                                    <span class="text-muted small d-block admin-ltr" dir="ltr">{{ $device->vehicle_number }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="{{ $isUserPanel ? 'sa-form-section' : 'card p-3 mb-3' }}">
            <h2 class="{{ $isUserPanel ? 'sa-form-section__title' : 'h6 mb-1' }}">{{ __('app.sub_accounts.permissions_section') }}</h2>
            <p class="{{ $isUserPanel ? 'sa-form-section__hint' : 'text-muted small mb-3' }}">
                {{ __('app.sub_accounts.granted_permissions_count', ['count' => $selectedPermissionCount]) }}
            </p>

            @if(empty($groupedPermissions) || $selectedPermissionCount === 0)
                <div class="alert alert-secondary mb-0">{{ __('app.sub_accounts.no_permissions_granted') }}</div>
            @else
                @foreach($groupedPermissions as $module => $tab)
                    @php
                        $modulePermissions = [];
                        foreach ($tab['categories'] as $groups) {
                            foreach ($groups as $group) {
                                foreach ($group['permissions'] as $permission) {
                                    if (isset($grantedKeys[$permission->key])) {
                                        $modulePermissions[] = $permission;
                                    }
                                }
                            }
                        }
                    @endphp
                    @continue(count($modulePermissions) === 0)

                    <div class="mb-3">
                        <h6 class="text-muted mb-2">{{ $tab['label'] }}</h6>
                        <div class="row g-2">
                            @foreach($modulePermissions as $permission)
                                <div class="col-md-6 col-lg-4">
                                    <div class="border rounded p-2 h-100 bg-light">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-check-circle text-success mt-1"></i>
                                            <div>
                                                <strong>{{ $permission->display_name }}</strong>
                                                @if($permission->description)
                                                    <span class="text-muted small d-block">{{ $permission->description }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </section>

    @if($isUserPanel)
        </div>
    @endif
@endsection
