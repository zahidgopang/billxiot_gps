@extends('admin.layouts.app')

@section('title', __('app.routes.title'))
@section('page-title', __('app.routes.title'))

@push('styles')
<style>
    .routes-index-card .table thead th {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #64748b;
        white-space: nowrap;
    }
    .routes-index-card .route-name-cell .route-path {
        font-size: 0.8rem;
        color: #64748b;
    }
    .routes-index-card .route-stat {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    .routes-index-card .route-actions .btn {
        min-width: 2.25rem;
    }
    @media (max-width: 767.98px) {
        .routes-index-card .route-actions .btn span.label {
            display: none;
        }
    }
</style>
@endpush

@section('content')
@php
    $hasFilters = ($search ?? '') !== '' || ($statusFilter ?? '') !== '';
    $indexUrl = route($panel . '.routes.index');
@endphp

<div class="card shadow-sm routes-index-card">
    <div class="card-header bg-white py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h5 class="mb-1">{{ __('app.routes.title') }}</h5>
                <p class="text-muted small mb-0">{{ __('app.routes.index_subtitle') }}</p>
            </div>
            @can('permission', 'routes.manage')
                <a href="{{ route($panel . '.routes.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i>{{ __('app.routes.create') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="card-body border-bottom bg-light py-3">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif

        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge bg-primary-subtle text-primary border">{{ __('app.common.total') }}: {{ $stats['total'] ?? 0 }}</span>
            <span class="badge bg-success-subtle text-success border">{{ __('app.common.active') }}: {{ $stats['active'] ?? 0 }}</span>
            @if($routes->total() !== ($stats['total'] ?? 0))
                <span class="badge bg-secondary-subtle text-secondary border">{{ __('app.routes.filtered') }}: {{ $routes->total() }}</span>
            @endif
        </div>

        <form method="get" action="{{ $indexUrl }}" class="admin-filter-bar row g-2 align-items-end">
            <div class="col-md-5 col-lg-4">
                <label class="form-label small mb-1" for="routes-filter-q">{{ __('app.common.search') }}</label>
                <input type="search"
                       name="q"
                       id="routes-filter-q"
                       value="{{ $search }}"
                       class="form-control form-control-sm"
                       placeholder="{{ __('app.routes.search_placeholder') }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label small mb-1" for="routes-filter-status">{{ __('app.common.status') }}</label>
                <select name="status" id="routes-filter-status" class="form-select form-select-sm">
                    <option value="">{{ __('app.routes.filter_status_all') }}</option>
                    <option value="active" @selected(($statusFilter ?? '') === 'active')>{{ __('app.common.active') }}</option>
                    <option value="inactive" @selected(($statusFilter ?? '') === 'inactive')>{{ __('app.common.inactive') }}</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-auto d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-search me-1"></i>{{ __('app.common.filter') }}
                </button>
                @if($hasFilters)
                    <a href="{{ $indexUrl }}" class="btn btn-sm btn-outline-secondary" title="{{ __('app.common.clear') }}">
                        <i class="fas fa-times me-1"></i>{{ __('app.common.clear') }}
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('app.routes.list_name') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('app.routes.list_distance') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('app.routes.list_duration') }}</th>
                    <th>{{ __('app.routes.checkpoints') }}</th>
                    <th class="d-none d-sm-table-cell">{{ __('app.routes.vehicles_assigned') }}</th>
                    <th>{{ __('app.common.status') }}</th>
                    <th class="text-end">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($routes as $route)
                    <tr>
                        <td class="route-name-cell">
                            <div class="fw-semibold">{{ $route->name }}</div>
                            <div class="route-path">
                                <i class="fas fa-route me-1 opacity-50"></i>{{ $route->routeLabel() }}
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            @if($route->expected_distance_km > 0)
                                {{ number_format((float) $route->expected_distance_km, 1) }} km
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell">
                            @if($route->expected_duration_minutes > 0)
                                {{ (int) $route->expected_duration_minutes }} {{ __('app.routes.minutes') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="route-stat" title="{{ __('app.routes.checkpoints') }}">
                                <i class="fas fa-map-pin text-primary opacity-75"></i>{{ $route->checkpoints_count }}
                            </span>
                        </td>
                        <td class="d-none d-sm-table-cell">
                            <span class="route-stat @if($route->assignments_count > 0) text-warning-emphasis @endif" title="{{ __('app.routes.vehicles_assigned') }}">
                                <i class="fas fa-truck text-secondary opacity-75"></i>{{ $route->assignments_count }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $route->status === 'active' ? 'success' : 'secondary' }}">
                                {{ $route->status === 'active' ? __('app.common.active') : __('app.common.inactive') }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap route-actions">
                            <a href="{{ route($panel . '.routes.edit', $route) }}"
                               class="btn btn-sm btn-outline-primary"
                               title="{{ __('app.common.edit') }}">
                                <i class="fas fa-edit"></i><span class="label ms-1 d-none d-md-inline">{{ __('app.common.edit') }}</span>
                            </a>
                            @can('permission', 'routes.manage')
                                @if($route->assignments_count > 0)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            disabled
                                            title="{{ __('app.routes.cannot_delete_assigned') }}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                @else
                                    <form action="{{ route($panel . '.routes.destroy', $route) }}" method="POST" class="d-inline route-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger route-delete-btn"
                                                data-route-name="{{ $route->name }}"
                                                title="{{ __('app.common.delete') }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="text-muted mb-2"><i class="fas fa-route fa-2x opacity-25"></i></div>
                            <p class="mb-1 fw-semibold">{{ __('app.routes.no_routes') }}</p>
                            <p class="small text-muted mb-3">{{ $hasFilters ? __('app.routes.no_routes_filtered') : __('app.routes.no_routes_hint') }}</p>
                            @if($hasFilters)
                                <a href="{{ $indexUrl }}" class="btn btn-sm btn-outline-secondary">{{ __('app.common.clear') }}</a>
                            @elseif(Gate::allows('permission', 'routes.manage'))
                                <a href="{{ route($panel . '.routes.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i>{{ __('app.routes.create') }}
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($routes->hasPages() || $routes->total() > 0)
        <div class="card-footer bg-white admin-pagination d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-2 py-3">
            <p class="small text-muted mb-0">
                {{ __('app.pagination.showing', [
                    'from' => $routes->firstItem() ?? 0,
                    'to' => $routes->lastItem() ?? 0,
                    'total' => $routes->total(),
                ]) }}
            </p>
            @if($routes->hasPages())
                {{ $routes->onEachSide(1)->links() }}
            @endif
        </div>
    @endif
</div>
@endsection

@can('permission', 'routes.manage')
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const confirmTitle = @json(__('app.routes.delete_confirm_named'));
    const cannotUndo = @json(__('app.common.cannot_undo'));
    const deleteLabel = @json(__('app.common.delete'));
    const cancelLabel = @json(__('app.common.cancel'));

    document.querySelectorAll('.route-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = this.closest('form');
            const name = this.dataset.routeName || '';
            const title = confirmTitle.replace(':name', name);

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    text: cannotUndo,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: deleteLabel,
                    cancelButtonText: cancelLabel,
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
                return;
            }

            if (window.confirm(title + '\n' + cannotUndo)) {
                form.submit();
            }
        });
    });
});
</script>
@endpush
@endcan
