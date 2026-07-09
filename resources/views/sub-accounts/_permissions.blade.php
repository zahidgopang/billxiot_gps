@php
    $oldKeys = collect(old('permission_keys', []))->flip()->all();
    $grantedKeys = $grantedKeys ?? [];
@endphp

@if(empty($groupedPermissions))
    <div class="alert alert-warning mb-0">
        {{ __('app.permissions.empty_catalog') }}
        <code>php artisan permissions:sync</code>
    </div>
@else
    <div class="mb-3">
        <div class="input-group" style="max-width: 360px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="search" id="subPermSearch" class="form-control form-control-sm"
                   placeholder="{{ __('app.permissions.search_placeholder') }}">
        </div>
    </div>

    <ul class="nav nav-tabs perm-tabs mb-3 flex-nowrap overflow-auto" role="tablist">
        @foreach($groupedPermissions as $module => $tab)
            <li class="nav-item" role="presentation">
                <button class="nav-link @if($loop->first) active @endif"
                        data-bs-toggle="tab"
                        data-bs-target="#sub-perm-tab-{{ $module }}"
                        type="button">
                    {{ $tab['label'] }}
                    <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $tab['count'] }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach($groupedPermissions as $module => $tab)
            <div class="tab-pane fade @if($loop->first) show active @endif" id="sub-perm-tab-{{ $module }}">
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-2">
                    <button type="button"
                            class="btn btn-link btn-sm p-0 js-sub-perm-select-section"
                            data-perm-scope="tab"
                            data-perm-target="sub-perm-tab-{{ $module }}">
                        {{ __('app.sub_accounts.select_all_permissions') }}
                    </button>
                    <span class="text-muted">|</span>
                    <button type="button"
                            class="btn btn-link btn-sm p-0 js-sub-perm-clear-section"
                            data-perm-scope="tab"
                            data-perm-target="sub-perm-tab-{{ $module }}">
                        {{ __('app.sub_accounts.unselect_all_permissions') }}
                    </button>
                </div>

                @foreach($tab['categories'] as $category => $groups)
                    @php $sectionId = 'sub-perm-section-'.md5($module.'|'.$category); @endphp
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-2">
                        <h6 class="text-muted mb-0">{{ $category }}</h6>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 js-sub-perm-select-section"
                                    data-perm-scope="section"
                                    data-perm-target="{{ $sectionId }}">
                                {{ __('app.sub_accounts.select_all_permissions') }}
                            </button>
                            <span class="text-muted">|</span>
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 js-sub-perm-clear-section"
                                    data-perm-scope="section"
                                    data-perm-target="{{ $sectionId }}">
                                {{ __('app.sub_accounts.unselect_all_permissions') }}
                            </button>
                        </div>
                    </div>

                    <div id="{{ $sectionId }}" data-perm-section>
                        @foreach($groups as $groupKey => $group)
                            @if($groupKey !== '_default' && count($groups) > 1)
                                <p class="small fw-semibold mb-2">{{ $group['label'] }}</p>
                            @endif
                            <div class="row g-2 mb-3">
                                @foreach($group['permissions'] as $permission)
                                    @php
                                        $key = $permission->key;
                                        $checked = isset($oldKeys[$key])
                                            || (empty($oldKeys) && empty(old()) && isset($grantedKeys[$key]));
                                    @endphp
                                    <div class="col-md-6 col-lg-4" data-perm-card data-perm-text="{{ $permission->display_name }} {{ $permission->description }}">
                                        <div class="form-check border rounded p-2 h-100">
                                            <input class="form-check-input" type="checkbox"
                                                   name="permission_keys[]"
                                                   id="perm-{{ md5($key) }}"
                                                   value="{{ $key }}"
                                                   @checked($checked)>
                                            <label class="form-check-label" for="perm-{{ md5($key) }}">
                                                <strong>{{ $permission->display_name }}</strong>
                                                <span class="text-muted small d-block">{{ $permission->description }}</span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function visiblePermissionBoxes(root) {
        return Array.from(root.querySelectorAll('input[name="permission_keys[]"]')).filter(function (box) {
            const card = box.closest('[data-perm-card]');
            return !card || !card.classList.contains('d-none');
        });
    }

    function setSectionChecked(targetId, checked) {
        const root = document.getElementById(targetId);
        if (!root) return;
        visiblePermissionBoxes(root).forEach(function (box) {
            if (!box.disabled) {
                box.checked = checked;
            }
        });
    }

    document.querySelectorAll('.js-sub-perm-select-section').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setSectionChecked(btn.getAttribute('data-perm-target'), true);
        });
    });

    document.querySelectorAll('.js-sub-perm-clear-section').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setSectionChecked(btn.getAttribute('data-perm-target'), false);
        });
    });
});
</script>
@endpush
@endonce
