@extends('admin.layouts.app')

@section('title', __('app.permissions.title'))
@section('page-title', __('app.permissions.title'))

@push('styles')
<link rel="stylesheet" href="{{ asset('css/permission-management.css') }}?v={{ @filemtime(public_path('css/permission-management.css')) ?: 1 }}">
@endpush

@section('content')
@php
    $isRoleMode = ($mode ?? 'role') === 'role';
@endphp

<div class="perm-mgmt">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link @if($isRoleMode) active @endif"
               href="{{ route($panel . '.permissions.index', ['mode' => 'role', 'role' => $selectedRole ?? 'admin']) }}">
                <i class="fas fa-users-cog me-1"></i>{{ __('app.permissions.mode_role') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if(! $isRoleMode) active @endif"
               href="{{ route($panel . '.permissions.index', ['mode' => 'user']) }}">
                <i class="fas fa-user me-1"></i>{{ __('app.permissions.mode_user') }}
            </a>
        </li>
    </ul>

    @if($isRoleMode)
        @include('admin.permissions._role_form')
    @else
        @include('admin.permissions._user_form')
    @endif
</div>
@endsection

@push('scripts')
@php
    $permSearchParams = array_filter([
        'mode' => $mode ?? 'role',
        'role' => $selectedRole ?? ($isRoleMode ? 'admin' : 'user'),
        'user_id' => (! $isRoleMode && ! empty($selectedUser)) ? $selectedUser->id : null,
    ]);
@endphp
<script>
window.PERM_MGMT_CONFIG = {
    mode: @json($mode ?? 'role'),
    searchUrl: @json(route($panel . '.permissions.index', $permSearchParams)),
    usersUrl: @json(route($panel . '.permissions.users')),
    templateUrl: @json(route($panel . '.permissions.template', ['slug' => '__SLUG__'])),
    csrfToken: @json(csrf_token()),
    labels: {
        granted: @json(__('app.permissions.granted')),
        noUsers: @json(__('app.permissions.no_users_for_role')),
    },
};
</script>
<script src="{{ asset('js/permission-management.js') }}?v={{ @filemtime(public_path('js/permission-management.js')) ?: 1 }}"></script>
@endpush
