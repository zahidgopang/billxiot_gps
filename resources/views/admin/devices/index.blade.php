@extends('admin.layouts.app')

@section('title', __('app.admin.devices.title'))
@section('page-title', __('app.admin.devices.title'))

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
    </style>
@endpush

@section('content')
    @php $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin'); @endphp
    <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">{{ __('app.admin.devices.title') }}</h5>
            <div>
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
            <table class="table table-hover table-small">
                <thead>
                <tr>
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
                    <tr>
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
@endsection

@push('scripts')
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ protected_js('device-status-toggle.js') }}"></script>

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

        });
    </script>
@endpush
