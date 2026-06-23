@extends('admin.layouts.app')
@section('title', __('app.common.users'))
@section('page-title', __('app.admin.users.page_title'))

@push('styles')
    <style>
        .skel-cell {
            display: table-cell;
            background: linear-gradient(90deg, #f3f3f3 25%, #ececec 37%, #f3f3f3 63%);
            background-size: 400% 100%;
            animation: sh 1.2s linear infinite;
            height: 42px; padding:15px;
        }
        @keyframes sh { 0%{background-position:200% 0}100%{background-position:-200% 0} }
        .device-status-toggle .form-check-input { cursor: pointer; width: 2.5em; height: 1.25em; }
        .device-status-toggle .form-check-input:disabled { cursor: not-allowed; }
        .user-name-badges { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; }
        .user-badge-new { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.02em; }
        .user-badge-not-linked { font-size: 0.7rem; font-weight: 600; }
    </style>
@endpush

@section('content')
    @php $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin'); @endphp
    <div class="card p-3">

        <div class="d-flex justify-content-between mb-3">
            <h5>{{ __('app.admin.users.title') }}</h5>
            <a href="{{ route($panel . '.users.create') }}" class="btn btn-primary btn-sm">{{ __('app.forms.add_user') }}</a>
        </div>

        <form class="admin-filter-bar d-flex flex-wrap gap-2 align-items-end mb-3" method="GET">
            <div class="flex-grow-1" style="min-width: 12rem; max-width: 24rem;">
                <label class="form-label small mb-1" for="users-filter-q">{{ __('app.common.search') }}</label>
                <input name="q" id="users-filter-q" value="{{ request('q') }}" class="form-control form-control-sm"
                       placeholder="{{ __('app.forms.search_name_email') }}">
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('app.common.search') }}</button>
                @if(request()->filled('q'))
                    <a href="{{ route($panel . '.users.index') }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.common.clear') }}">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </form>

        <!-- Skeleton -->
        <div id="skeleton-area">
            @for($i=0;$i<6;$i++)
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <div class="skel-cell" style="width:25%"></div>
                    <div class="skel-cell" style="width:25%"></div>
                    <div class="skel-cell" style="width:20%"></div>
                    <div class="skel-cell" style="width:15%"></div>
                    <div class="skel-cell" style="width:15%"></div>
                </div>
            @endfor
        </div>

        <!-- Real data -->
        <div id="real-area" style="display:none;">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>{{ __('app.forms.name') }}</th>
                    <th>{{ __('app.forms.email') }}</th>
                    <th>{{ __('app.forms.role') }}</th>
                    @if($panel === 'admin')
                        <th>{{ __('app.admin.users.client_link') }}</th>
                    @endif
                    <th>{{ __('app.common.status') }}</th>
                    <th>{{ __('app.forms.joined') }}</th>
                    <th>{{ __('app.common.actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($users as $u)
                    @php
                        $roleEnum = \App\Enums\AppRole::tryFrom($u->role);
                        $isEndUser = $u->isEndUserRole();
                        $isNew = $u->isNewRegistration();
                        $isLinked = $u->isLinkedToAnyClient();
                    @endphp
                    <tr>
                        <td>
                            <div class="user-name-badges">
                                <span>{{ $u->name }}</span>
                                @if($isNew)
                                    <span class="badge bg-success user-badge-new"
                                          title="{{ __('app.admin.users.badge_new_title') }}">{{ __('app.admin.users.badge_new') }}</span>
                                @endif
                                @if($isEndUser && ! $isLinked)
                                    <span class="badge bg-warning text-dark user-badge-not-linked"
                                          title="{{ __('app.admin.users.badge_not_linked_title') }}">{{ __('app.admin.users.badge_not_linked') }}</span>
                                @endif
                            </div>
                        </td>
                        <td><x-admin.ltr>{{ $u->email }}</x-admin.ltr></td>
                        <td>{{ $roleEnum?->label() ?? $u->role }}</td>
                        @if($panel === 'admin')
                            <td>
                                @if($isEndUser)
                                    @if($isLinked)
                                        <span class="text-muted small">{{ $u->primaryClientName() ?? '—' }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark user-badge-not-linked">{{ __('app.admin.users.badge_not_linked') }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endif
                        <td>
                            @include('partials.account-status-toggle', [
                                'user' => $u,
                                'toggleUrl' => route($panel . '.users.toggle-status', $u),
                                'toggleDisabled' => $u->id === auth()->id() || $u->email === 'admin@demo.test',
                            ])
                        </td>
                        <td><x-admin.ltr>{{ $u->created_at?->diffForHumans() ?? '—' }}</x-admin.ltr></td>
                        <td>
                            <a href="{{ route($panel . '.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">{{ __('app.common.edit') }}</a>

                            @if($isEndUser && ($u->tracker_devices_count ?? 0) > 0 && Gate::allows('permission', 'maps.view'))
                                <a href="{{ route($panel . '.users.fleet-map', $u) }}"
                                   class="btn btn-sm btn-primary"
                                   title="{{ __('app.admin.users.track_devices_title') }}">
                                    <i class="fas fa-map-marked-alt me-1"></i>{{ __('app.admin.nav.track_devices') }}
                                </a>
                            @endif

                            @if($panel === 'admin')
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="d-inline delete-form">
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
                {{ $users->links() }}
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="{{ protected_js('device-status-toggle.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            skeleton = document.getElementById('skeleton-area');
            real = document.getElementById('real-area');
            skeleton.style.display = 'none';
            real.style.display = '';

            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function(){
                    const form = this.closest('form');
                    Swal.fire({
                        title: @json(__('app.forms.delete_user_confirm')),
                        text: @json(__('app.common.cannot_undo')),
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: @json(__('app.common.delete')),
                        cancelButtonText: @json(__('app.common.cancel')),
                    }).then((r)=>{
                        if(r.isConfirmed){ form.submit(); }
                    });
                });
            });

        });
    </script>
@endpush
