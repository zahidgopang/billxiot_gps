@php
    $isCreate = ! ($subAccount?->exists ?? false);
    $selectedDeviceIds = collect(old('device_ids', $selectedDeviceIds ?? []))->map(fn ($id) => (string) $id)->all();
    $grantedKeys = $grantedKeys ?? [];
    $essentialKeys = $essentialKeys ?? [];
@endphp

<section class="sa-form-section">
    <h2 class="sa-form-section__title">{{ __('app.forms.basic_information') }}</h2>
    <div class="sa-field-grid">
        <div class="sa-field">
            <label class="form-label" for="sub-name">{{ __('app.common.name') }}</label>
            <input type="text" name="name" id="sub-name" class="form-control"
                   value="{{ old('name', $subAccount?->name ?? '') }}" required>
            @error('name') <p class="text-danger small mb-0 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sa-field">
            <label class="form-label" for="sub-email">{{ __('app.forms.email') }}</label>
            <input type="email" name="email" id="sub-email" class="form-control admin-ltr" dir="ltr"
                   value="{{ old('email', $subAccount?->email ?? '') }}" required autocomplete="email">
            <p class="text-muted small mb-0 mt-1">{{ __('app.sub_accounts.email_login_hint') }}</p>
            @error('email') <p class="text-danger small mb-0 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sa-field">
            <label class="form-label" for="sub-password">
                {{ __('app.forms.password') }}
                @if($isCreate)<span class="text-danger">*</span>@endif
            </label>
            <input type="password" name="password" id="sub-password" class="form-control"
                   @if($isCreate) required @endif autocomplete="new-password">
            @if(! $isCreate)
                <p class="text-muted small mb-0 mt-1">{{ __('app.forms.password_keep_hint') }}</p>
            @endif
            @error('password') <p class="text-danger small mb-0 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sa-field">
            <label class="form-label" for="sub-status">{{ __('app.forms.account_status') }}</label>
            @php $st = old('status', $subAccount?->status ?? 'active'); @endphp
            <select name="status" id="sub-status" class="form-select">
                <option value="active" @selected($st === 'active')>{{ __('app.common.active') }}</option>
                <option value="inactive" @selected($st === 'inactive')>{{ __('app.common.inactive') }}</option>
            </select>
            @error('status') <p class="text-danger small mb-0 mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="sa-field sa-field--full">
            @include('partials.country-phone-input', [
                'idPrefix' => 'sub-account',
                'countryCodeValue' => old('country_code', $subAccount?->country_code),
                'phoneValue' => old('phone', $subAccount?->phone),
                'grid' => true,
            ])
        </div>
    </div>
</section>

<section class="sa-form-section">
    <h2 class="sa-form-section__title">{{ __('app.sub_accounts.vehicles_section') }}</h2>
    <p class="sa-form-section__hint">{{ __('app.sub_accounts.vehicles_hint') }}</p>

    @if($devices->isEmpty())
        <div class="alert alert-warning mb-0">{{ __('app.sub_accounts.no_vehicles') }}</div>
    @else
        <div class="sa-device-grid">
            @foreach($devices as $device)
                @php $deviceId = (string) $device->id; @endphp
                <label class="sa-device-option" for="device-{{ $device->id }}">
                    <input class="form-check-input" type="checkbox" name="device_ids[]" id="device-{{ $device->id }}"
                           value="{{ $device->id }}"
                           @checked(in_array($deviceId, $selectedDeviceIds, true))>
                    <span class="sa-device-option__body">
                        <strong>{{ $device->vehicle_name ?: $device->name }}</strong>
                        @if($device->vehicle_number)
                            <span class="text-muted small d-block admin-ltr" dir="ltr">{{ $device->vehicle_number }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        <div class="mt-3">
            <button type="button" class="btn btn-link btn-sm p-0" id="sub-select-all-devices">{{ __('app.sub_accounts.select_all_vehicles') }}</button>
            <span class="text-muted mx-1">|</span>
            <button type="button" class="btn btn-link btn-sm p-0" id="sub-clear-all-devices">{{ __('app.sub_accounts.clear_vehicles') }}</button>
        </div>
    @endif
    @error('device_ids') <p class="text-danger small mt-2 mb-0">{{ $message }}</p> @enderror
    @error('device_ids.*') <p class="text-danger small mt-2 mb-0">{{ $message }}</p> @enderror
</section>

<section class="sa-form-section">
    <h2 class="sa-form-section__title">{{ __('app.sub_accounts.permissions_section') }}</h2>
    <p class="sa-form-section__hint">{{ __('app.sub_accounts.permissions_hint') }}</p>

    @include('sub-accounts._permissions', [
        'groupedPermissions' => $groupedPermissions ?? [],
        'grantedKeys' => $grantedKeys,
        'essentialKeys' => $essentialKeys,
    ])
</section>

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
