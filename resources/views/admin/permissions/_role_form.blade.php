<form method="post" action="{{ route($panel . '.permissions.update') }}" id="permissionForm">
    @csrf
    @method('PUT')

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="permRoleSelect">{{ __('app.permissions.role') }}</label>
                    <select id="permRoleSelect" class="form-select">
                        @foreach($roles as $roleOption)
                            <option value="{{ $roleOption['value'] }}" @selected($selectedRole === $roleOption['value'])>{{ $roleOption['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="role" value="{{ $selectedRole }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-semibold" for="permSearch">{{ __('app.permissions.search') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="search" id="permSearch" class="form-control" value="{{ $search }}" placeholder="{{ __('app.permissions.search_placeholder') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(empty($grouped))
        <div class="alert alert-warning">
            {{ __('app.permissions.empty_catalog') }}
            <code class="ms-1">php artisan permissions:sync</code>
        </div>
    @else
        @include('admin.permissions._matrix', [
            'inputType' => 'checkbox',
            'grantedKeys' => $grantedKeys,
        ])

        <div class="perm-mgmt__footer card shadow-sm border-0 py-3 px-3 mt-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>{{ __('app.permissions.save_role') }}
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" id="permSelectAll">
                    <i class="fas fa-check-double me-1"></i>{{ __('app.permissions.select_all') }}
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="permClearAll">
                    <i class="fas fa-times me-1"></i>{{ __('app.permissions.unselect_all') }}
                </button>
                <span class="small text-muted ms-auto" id="permStatGranted">
                    {{ __('app.permissions.granted') }}: <strong>{{ $grantedCount }}</strong> / {{ $totalCount }}
                </span>
            </div>
        </div>
    @endif
</form>
