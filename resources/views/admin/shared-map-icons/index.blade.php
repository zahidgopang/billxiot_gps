@extends('admin.layouts.app')

@section('title', __('app.admin.shared_map_icons_title') . ' - ' . __('app.brand'))
@section('page-title', __('app.admin.shared_map_icons_title'))

@section('content')
@php
    $categoryLabels = collect($categories)->mapWithKeys(fn ($c) => [$c['id'] => $c['label']]);
@endphp
<div class="container-fluid py-3">
    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2">
            <ul class="mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h5 class="mb-1">{{ __('app.admin.shared_map_icons_title') }}</h5>
            <p class="small text-muted mb-3">{{ __('app.admin.shared_map_icons_hint') }}</p>

            <form method="post" action="{{ route('admin.shared-map-icons.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="sharedIconCategory">{{ __('app.map.shared_icon_category') }}</label>
                    <select name="category" id="sharedIconCategory" class="form-select form-select-sm" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat['id'] }}" @selected(old('category', $activeCategory) === $cat['id'])>{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1" for="sharedIconFile">{{ __('app.map.shared_icon_file') }}</label>
                    <input type="file" name="icon[]" id="sharedIconFile" class="form-control form-control-sm" accept=".png,.svg,image/png,image/svg+xml" multiple required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="sharedIconLabel">{{ __('app.map.shared_icon_label') }}</label>
                    <input type="text" name="label" id="sharedIconLabel" class="form-control form-control-sm" value="{{ old('label') }}" maxlength="120" placeholder="{{ __('app.map.shared_icon_label_placeholder') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1" for="sharedIconOrientation">{{ __('app.map.icon_default_orientation') }}</label>
                    <select name="rotation_offset" id="sharedIconOrientation" class="form-select form-select-sm">
                        <option value="0" @selected((string) old('rotation_offset', '0') === '0')>{{ __('app.map.icon_orient_north') }}</option>
                        <option value="-90" @selected((string) old('rotation_offset', '0') === '-90')>{{ __('app.map.icon_orient_east') }}</option>
                        <option value="180" @selected((string) old('rotation_offset', '0') === '180')>{{ __('app.map.icon_orient_south') }}</option>
                        <option value="90" @selected((string) old('rotation_offset', '0') === '90')>{{ __('app.map.icon_orient_west') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload me-1"></i>{{ __('app.map.shared_icon_upload') }}
                    </button>
                </div>
            </form>
            <p class="small text-muted mt-2 mb-0">{{ __('app.map.shared_icon_upload_hint') }}</p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="mb-0">{{ __('app.admin.shared_map_icons_library') }}</h6>
                <div class="btn-group btn-group-sm" role="group">
                    @foreach($categories as $cat)
                        @php
                            $count = $allIcons->where('category', $cat['id'])->count();
                        @endphp
                        <a href="{{ route('admin.shared-map-icons.index', ['category' => $cat['id']]) }}"
                           class="btn {{ $activeCategory === $cat['id'] ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ $cat['label'] }}
                            <span class="badge bg-light text-dark ms-1">{{ $count }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @if($icons->isEmpty())
                <p class="text-muted small mb-0">{{ __('app.map.no_icon_found') }}</p>
            @else
                <div class="row g-3">
                    @foreach($icons as $icon)
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                            <div class="border rounded p-2 h-100 d-flex flex-column align-items-center text-center">
                                <img src="{{ \App\Support\VehicleIcons\SharedMapIconStorage::urlForRelativePath($icon->relative_path) }}"
                                     alt="{{ $icon->label }}"
                                     width="64" height="64"
                                     style="object-fit:contain;background:#f8fafc;border-radius:8px;">
                                <div class="small fw-semibold mt-2 text-truncate w-100" title="{{ $icon->label }}">{{ $icon->label }}</div>
                                <div class="text-muted" style="font-size:0.7rem;">{{ $categoryLabels[$icon->category] ?? $icon->category }}</div>
                                <form method="post" action="{{ route('admin.shared-map-icons.update', $icon) }}" class="mt-2 w-100">
                                    @csrf
                                    @method('PATCH')
                                    <label class="visually-hidden" for="orient{{ $icon->id }}">{{ __('app.map.icon_default_orientation') }}</label>
                                    <select name="rotation_offset" id="orient{{ $icon->id }}" class="form-select form-select-sm mb-1" onchange="this.form.submit()">
                                        @php $off = (string) $icon->signedRotationOffset(); @endphp
                                        <option value="0" @selected($off === '0')>{{ __('app.map.icon_orient_north') }}</option>
                                        <option value="-90" @selected($off === '-90')>{{ __('app.map.icon_orient_east') }}</option>
                                        <option value="180" @selected($off === '180')>{{ __('app.map.icon_orient_south') }}</option>
                                        <option value="90" @selected($off === '90')>{{ __('app.map.icon_orient_west') }}</option>
                                    </select>
                                </form>
                                <form method="post" action="{{ route('admin.shared-map-icons.destroy', $icon) }}" class="mt-1"
                                      onsubmit="return confirm(@json(__('app.map.shared_icon_delete_confirm')));">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
