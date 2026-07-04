<form method="get" action="{{ route($panel . '.permissions.index') }}" id="permUserPicker" class="card shadow-sm mb-3">
    <input type="hidden" name="mode" value="user">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="permUserRoleSelect">{{ __('app.permissions.role') }}</label>
                <select id="permUserRoleSelect" name="role" class="form-select"
                        onchange="var u=this.form.querySelector('[name=user_id]'); if(u) u.removeAttribute('name'); this.form.submit();">
                    @foreach($roles as $roleOption)
                        @php $count = $roleUserCounts[$roleOption['value']] ?? 0; @endphp
                        <option value="{{ $roleOption['value'] }}" @selected(($selectedRole ?? 'user') === $roleOption['value'])>
                            {{ $roleOption['label'] }} ({{ $count }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold" for="permUserSelect">{{ __('app.permissions.user') }}</label>
                @if($usersForSelect->isNotEmpty())
                    <select id="permUserSelect" name="user_id" class="form-select" onchange="this.form.submit()">
                        @foreach($usersForSelect as $userOption)
                            <option value="{{ $userOption->id }}" @selected($selectedUser && $selectedUser->id === $userOption->id)>
                                {{ $userOption->name }} — {{ $userOption->email }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <select id="permUserSelect" class="form-select" disabled>
                        <option value="">{{ __('app.permissions.no_users_for_role') }}</option>
                    </select>
                @endif
            </div>
        </div>
    </div>
</form>

@if($usersForSelect->isEmpty())
    <div class="card shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-users fa-2x mb-3 opacity-25"></i>
            <p class="mb-0">{{ __('app.permissions.no_users_for_role_hint') }}</p>
        </div>
    </div>
@elseif($selectedUser)
<form method="post"
      action="{{ route($panel . '.permissions.user.update', $selectedUser) }}"
      id="permissionUserForm">
    @csrf
    @method('PUT')

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="fw-semibold">{{ $selectedUser->name }}</span>
                    <span class="text-muted small ms-2">{{ $selectedUser->email }}</span>
                </div>
                <div class="input-group" style="max-width: 320px;">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="search" id="permSearch" class="form-control" placeholder="{{ __('app.permissions.search_placeholder') }}">
                </div>
            </div>
            <p class="small text-muted mb-0 mt-2">{{ __('app.permissions.user_simple_help') }}</p>
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
                    <i class="fas fa-save me-1"></i>{{ __('app.permissions.save_user') }}
                </button>
                <span class="small text-muted ms-auto" id="permStatGranted">
                    {{ __('app.permissions.granted') }}: <strong>{{ $grantedCount }}</strong> / {{ $totalCount }}
                </span>
            </div>
        </div>
    @endif
</form>
@endif
