@php
    $isUserPanel = ($panel ?? 'admin') === 'user';
    $layout = $isUserPanel ? 'user.layout_user' : 'admin.layouts.app';
    $canManage = auth()->user()?->canManageSubAccounts() ?? false;
    $canView = auth()->user()?->canViewSubAccounts() ?? false;
@endphp

@extends($layout)

@section('title', __('app.sub_accounts.title'))
@if(! $isUserPanel)
@section('page-title', __('app.sub_accounts.title'))
@endif

@section('content')
    @php $panel = $panel ?? 'admin'; @endphp

    @if($isUserPanel)
        <div class="container dashboard-container">
            <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="fas fa-user-group me-2" style="color: var(--primary-blue);"></i>
                        {{ __('app.sub_accounts.title') }}
                    </h4>
                    <p class="text-muted mb-0">{{ __('app.sub_accounts.subtitle') }}</p>
                </div>
                @if(auth()->user()?->canCreateSubAccounts())
                <a href="{{ route('user.sub-accounts.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i>{{ __('app.sub_accounts.add') }}
                </a>
                @endif
            </div>
    @else
        <div class="card p-3">
            <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
                <h5 class="mb-0">{{ __('app.sub_accounts.title') }}</h5>
                @if(auth()->user()?->canCreateSubAccounts())
                <a href="{{ route($panel . '.sub-accounts.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i>{{ __('app.sub_accounts.add') }}
                </a>
                @endif
            </div>
    @endif

        <form class="admin-filter-bar d-flex flex-wrap gap-2 align-items-end mb-3" method="GET">
            <div class="flex-grow-1" style="min-width: 12rem; max-width: 24rem;">
                <label class="form-label small mb-1" for="sub-accounts-q">{{ __('app.common.search') }}</label>
                <input name="q" id="sub-accounts-q" value="{{ request('q') }}" class="form-control form-control-sm"
                       placeholder="{{ __('app.forms.search_name_email') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('app.common.search') }}</button>
            @if(request()->filled('q'))
                <a href="{{ route($panel . '.sub-accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times"></i>
                </a>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.common.name') }}</th>
                        <th>{{ __('app.forms.email') }}</th>
                        <th>{{ __('app.sub_accounts.vehicles') }}</th>
                        <th>{{ __('app.common.status') }}</th>
                        <th class="text-end">{{ __('app.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subAccounts as $account)
                        <tr>
                            <td>{{ $account->name }}</td>
                            <td class="admin-ltr" dir="ltr">{{ $account->email }}</td>
                            <td>{{ $account->tracker_devices_count ?? 0 }}</td>
                            <td>
                                <span class="badge {{ $account->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $account->status === 'active' ? __('app.common.active') : __('app.common.inactive') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    @if($canView)
                                        <a href="{{ route($panel . '.sub-accounts.show', $account) }}"
                                           class="btn btn-outline-secondary btn-sm"
                                           title="{{ __('app.common.view') }}">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                    @if($canManage)
                                        <a href="{{ route($panel . '.sub-accounts.edit', $account) }}"
                                           class="btn btn-outline-primary btn-sm"
                                           title="{{ __('app.common.edit') }}">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST"
                                              action="{{ route($panel . '.sub-accounts.destroy', $account) }}"
                                              class="d-inline js-sub-account-delete"
                                              data-name="{{ $account->name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-outline-danger btn-sm"
                                                    title="{{ __('app.common.delete') }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @elseif(! $canView)
                                        <span class="text-muted small">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                {{ __('app.sub_accounts.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subAccounts->hasPages())
            <div class="mt-3">{{ $subAccounts->links() }}</div>
        @endif

    @if($isUserPanel)
        </div>
    @else
        </div>
    @endif
@endsection

@push('scripts')
    @if($isUserPanel)
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-sub-account-delete').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    const name = form.getAttribute('data-name') || '';
                    const title = @json(__('app.sub_accounts.delete_confirm_title'));
                    const text = (@json(__('app.sub_accounts.delete_confirm_named'))).replace(':name', name);
                    const confirmText = @json(__('app.common.yes_delete'));
                    const cancelText = @json(__('app.common.cancel'));

                    if (typeof Swal === 'undefined') {
                        if (window.confirm(title + '\n' + text)) {
                            form.submit();
                        }
                        return;
                    }

                    Swal.fire({
                        title: title,
                        text: text,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#DC2626',
                        cancelButtonColor: '#64748B',
                        confirmButtonText: confirmText,
                        cancelButtonText: cancelText,
                        reverseButtons: true,
                        focusCancel: true,
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        });
    </script>
@endpush
