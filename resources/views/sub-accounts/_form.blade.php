@php
    $isCreate = ! ($subAccount?->exists ?? false);
    $selectedDeviceIds = collect(old('device_ids', $selectedDeviceIds ?? []))->map(fn ($id) => (string) $id)->all();
    $grantedKeys = $grantedKeys ?? [];
    $essentialKeys = $essentialKeys ?? [];
@endphp

<x-admin.form-section :title="__('app.forms.basic_information')" icon="fas fa-user">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="admin-label" for="sub-name">{{ __('app.common.name') }}</label>
            <input type="text" name="name" id="sub-name" class="form-control form-control-sm"
                   value="{{ old('name', $subAccount?->name ?? '') }}" required>
            @error('name') <p class="text-danger small mb-0">{{ $message }}</p> @enderror
        </div>
        <div class="col-md-6">
            <label class="admin-label" for="sub-email">{{ __('app.forms.email') }}</label>
            <input type="email" name="email" id="sub-email" class="form-control form-control-sm admin-ltr" dir="ltr"
                   value="{{ old('email', $subAccount?->email ?? '') }}" required autocomplete="email">
            <p class="admin-hint">{{ __('app.sub_accounts.email_login_hint') }}</p>
            @error('email') <p class="text-danger small mb-0">{{ $message }}</p> @enderror
        </div>
        <div class="col-md-6">
            <label class="admin-label" for="sub-password">
                {{ __('app.forms.password') }}
                @if($isCreate)<span class="text-danger">*</span>@endif
            </label>
            <input type="password" name="password" id="sub-password" class="form-control form-control-sm"
                   @if($isCreate) required @endif autocomplete="new-password">
            @if(! $isCreate)
                <p class="admin-hint">{{ __('app.forms.password_keep_hint') }}</p>
            @endif
            @error('password') <p class="text-danger small mb-0">{{ $message }}</p> @enderror
        </div>
        <div class="col-md-6">
            <label class="admin-label" for="sub-status">{{ __('app.forms.account_status') }}</label>
            @php $st = old('status', $subAccount?->status ?? 'active'); @endphp
            <select name="status" id="sub-status" class="form-select form-select-sm">
                <option value="active" @selected($st === 'active')>{{ __('app.common.active') }}</option>
                <option value="inactive" @selected($st === 'inactive')>{{ __('app.common.inactive') }}</option>
            </select>
            @error('status') <p class="text-danger small mb-0">{{ $message }}</p> @enderror
        </div>
        <div class="col-12">
            @include('partials.country-phone-input', [
                'idPrefix' => 'sub-account',
                'countryCodeValue' => old('country_code', $subAccount?->country_code),
                'phoneValue' => old('phone', $subAccount?->phone),
                'grid' => true,
            ])
        </div>
    </div>
</x-admin.form-section>

<x-admin.form-section :title="__('app.sub_accounts.vehicles_section')" icon="fas fa-car" :description="__('app.sub_accounts.vehicles_hint')">
    @if($devices->isEmpty())
        <div class="alert alert-warning mb-0">{{ __('app.sub_accounts.no_vehicles') }}</div>
    @else
        <div class="row g-2">
            @foreach($devices as $device)
                @php $deviceId = (string) $device->id; @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="form-check border rounded p-2 h-100">
                        <input class="form-check-input" type="checkbox" name="device_ids[]" id="device-{{ $device->id }}"
                               value="{{ $device->id }}"
                               @checked(in_array($deviceId, $selectedDeviceIds, true))>
                        <label class="form-check-label" for="device-{{ $device->id }}">
                            <strong>{{ $device->vehicle_name ?: $device->name }}</strong>
                            @if($device->vehicle_number)
                                <span class="text-muted small d-block admin-ltr" dir="ltr">{{ $device->vehicle_number }}</span>
                            @endif
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-2">
            <button type="button" class="btn btn-link btn-sm p-0" id="sub-select-all-devices">{{ __('app.sub_accounts.select_all_vehicles') }}</button>
            <span class="text-muted mx-1">|</span>
            <button type="button" class="btn btn-link btn-sm p-0" id="sub-clear-all-devices">{{ __('app.sub_accounts.clear_vehicles') }}</button>
        </div>
    @endif
    @error('device_ids') <p class="text-danger small mt-2 mb-0">{{ $message }}</p> @enderror
    @error('device_ids.*') <p class="text-danger small mt-2 mb-0">{{ $message }}</p> @enderror
</x-admin.form-section>

<x-admin.form-section :title="__('app.sub_accounts.permissions_section')" icon="fas fa-shield-halved" :description="__('app.sub_accounts.permissions_hint')">
    @include('sub-accounts._permissions', [
        'groupedPermissions' => $groupedPermissions ?? [],
        'grantedKeys' => $grantedKeys,
        'essentialKeys' => $essentialKeys,
    ])
</x-admin.form-section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('sub-select-all-devices');
    const clearAll = document.getElementById('sub-clear-all-devices');
    const boxes = document.querySelectorAll('input[name="device_ids[]"]');

    if (selectAll) {
        selectAll.addEventListener('click', function () {
            boxes.forEach(function (box) { box.checked = true; });
        });
    }
    if (clearAll) {
        clearAll.addEventListener('click', function () {
            boxes.forEach(function (box) { box.checked = false; });
        });
    }

    const permSearch = document.getElementById('subPermSearch');
    if (permSearch) {
        permSearch.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('[data-perm-card]').forEach(function (card) {
                const text = (card.getAttribute('data-perm-text') || '').toLowerCase();
                card.classList.toggle('d-none', q !== '' && !text.includes(q));
            });
        });
    }
});
</script>
@endpush
