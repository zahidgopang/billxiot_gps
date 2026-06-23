@php
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $isCreate = ! ($user?->exists ?? false);
    $rbac = app(\App\Services\Authorization\RbacService::class);
    $defaultRole = ($assignableRoles ?? [])[0] ?? \App\Enums\AppRole::EndUser->value;
    $selectedRole = old('role', $user?->role ?? $defaultRole);
    $endUserRole = \App\Enums\AppRole::EndUser->value;
    $showMapTrackingToggle = $panel === 'admin' && $rbac->roleSupportsMapTrackingToggle($selectedRole);
    $showClientPicker = $panel === 'admin'
        && !empty($clients)
        && $clients->count()
        && $selectedRole === $endUserRole;
    $clientRoleSelected = $selectedRole === \App\Enums\AppRole::Client->value;
    $linkedClient = ($user?->exists ?? false) && $clientRoleSelected
        ? $user->clients()->first()
        : null;
    $canTrackMaps = (bool) old(
        'can_track_maps',
        ($user?->exists ?? false) && $rbac->roleSupportsMapTrackingToggle($user->role)
            ? $rbac->hasPermission($user, 'maps.view')
            : false
    );
@endphp

<x-admin.form-section
    :title="__('app.forms.basic_information')"
    icon="fas fa-user"
    :description="$isCreate ? __('app.forms.user_basic_hint') : null"
>
    <x-admin.form-col>
        <label class="admin-label" for="user-name">{{ __('app.forms.name') }}</label>
        <input type="text" name="name" id="user-name" value="{{ old('name', $user?->name ?? '') }}" class="form-control form-control-sm" required>
        @error('name') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="user-email">{{ __('app.forms.email') }}</label>
        <input type="email" name="email" id="user-email" value="{{ old('email', $user?->email ?? '') }}" class="form-control form-control-sm admin-ltr" dir="ltr" required>
        @error('email') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.forms.account_settings')"
    icon="fas fa-key"
    :description="__('app.forms.account_settings_hint')"
>
    <x-admin.form-col>
        <label class="admin-label" for="user-password">
            {{ __('app.forms.password') }}
            @if($isCreate)<span class="text-danger">*</span>@endif
        </label>
        <input type="password" name="password" id="user-password" class="form-control form-control-sm" @if($isCreate) required @endif autocomplete="new-password">
        @if($isCreate)
            <p class="admin-hint">{{ __('app.forms.password_min_hint') }}</p>
        @else
            <p class="admin-hint">{{ __('app.forms.password_keep_hint') }}</p>
        @endif
        @error('password') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col :full="true">
        @include('partials.country-phone-input', [
            'idPrefix' => 'admin-user',
            'countryCodeValue' => old('country_code', $user?->country_code),
            'phoneValue' => old('phone', $user?->phone),
            'grid' => true,
        ])
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.forms.access_permissions')"
    icon="fas fa-shield-halved"
    :description="__('app.forms.access_permissions_hint')"
>
    @if($panel === 'client')
        <input type="hidden" name="role" value="{{ \App\Enums\AppRole::EndUser->value }}">
    @else
        <x-admin.form-col>
            <label class="admin-label" for="user-role">{{ __('app.forms.role') }}</label>
            <select name="role" id="user-role" class="form-select form-select-sm" data-search="false" data-end-user-role="{{ $endUserRole }}">
                @php $r = $selectedRole; @endphp
                @foreach(($assignableRoles ?? ['user']) as $roleValue)
                    @php $roleEnum = \App\Enums\AppRole::tryFrom($roleValue); @endphp
                    <option value="{{ $roleValue }}" @selected($r === $roleValue)>{{ $roleEnum?->label() ?? $roleValue }}</option>
                @endforeach
            </select>
            @error('role') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        </x-admin.form-col>
    @endif

    @if($panel === 'admin' && !empty($clients) && $clients->count())
        <x-admin.form-col id="user-client-company-field" :full="true" @class(['d-none' => ! $showClientPicker])>
            <label class="admin-label" for="user-client-id">
                {{ __('app.forms.client_company') }}
                <span id="user-client-required-mark" class="text-danger @if(! $showClientPicker) d-none @endif" aria-hidden="true">*</span>
            </label>
            <select
                id="user-client-id"
                class="form-select form-select-sm @if(! $showClientPicker) no-select2 @endif"
                data-search="false"
                @if($showClientPicker) name="client_id" required @endif
                @if(! $showClientPicker) disabled tabindex="-1" aria-hidden="true" @endif
            >
                <option value="" @selected(!old('client_id') && !($user?->exists ?? false))>{{ __('app.forms.select_client') }}</option>
                @php
                    $selectedClient = old('client_id', ($user?->exists ?? false) ? optional($user->clients()->first())->id : null);
                @endphp
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) $selectedClient === (string) $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            <p id="user-client-hint" class="admin-hint @if(! $showClientPicker) d-none @endif">{{ __('app.forms.client_picker_hint') }}</p>
            @error('client_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        </x-admin.form-col>

        <x-admin.form-col id="user-client-auto-company-field" :full="true" @class(['d-none' => ! $clientRoleSelected])>
            @if($linkedClient)
                <p class="admin-label mb-1">{{ __('app.forms.client_company') }}</p>
                <p class="mb-0"><strong>{{ $linkedClient->name }}</strong></p>
                <p class="admin-hint mt-2">{{ __('app.forms.client_role_company_linked_hint') }}</p>
            @else
                <p class="admin-hint mb-0">{{ __('app.forms.client_role_company_auto_hint') }}</p>
            @endif
        </x-admin.form-col>
    @endif

    <x-admin.form-col>
        <label class="admin-label" for="user-status">{{ __('app.forms.account_status') }}</label>
        <select name="status" id="user-status" class="form-select form-select-sm" data-search="false">
            @php $st = old('status', $user?->status ?? 'active'); @endphp
            <option value="active" @selected($st === 'active')>{{ __('app.common.active') }}</option>
            <option value="inactive" @selected($st === 'inactive')>{{ __('app.common.inactive') }}</option>
        </select>
        <p class="admin-hint">{{ __('app.forms.inactive_user_hint') }}</p>
        @error('status') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    @if($panel === 'admin')
        <x-admin.form-col :full="true" id="user-map-tracking-field" @class(['d-none' => ! $showMapTrackingToggle])>
            <input type="hidden" name="can_track_maps" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="can_track_maps" id="user-can-track-maps" value="1"
                       @checked($canTrackMaps)>
                <label class="form-check-label admin-label mb-0" for="user-can-track-maps">
                    {{ __('app.forms.user_can_track_maps_label') }}
                </label>
            </div>
            <p class="admin-hint mt-2">{{ __('app.forms.user_can_track_maps_hint') }}</p>
            @error('can_track_maps') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        </x-admin.form-col>
    @endif
</x-admin.form-section>

@if($panel === 'admin')
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleSelect = document.getElementById('user-role');
            const mapField = document.getElementById('user-map-tracking-field');
            const mapCheckbox = document.getElementById('user-can-track-maps');
            const clientPickerField = document.getElementById('user-client-company-field');
            const clientAutoField = document.getElementById('user-client-auto-company-field');
            const clientSelect = document.getElementById('user-client-id');
            const clientRequiredMark = document.getElementById('user-client-required-mark');
            const userForm = roleSelect.closest('form');
            if (!roleSelect) return;

            const trackableRoles = @json([
                \App\Enums\AppRole::Admin->value,
                \App\Enums\AppRole::Client->value,
            ]);
            const endUserRole = roleSelect.getAttribute('data-end-user-role') || @json($endUserRole);
            const clientRole = @json(\App\Enums\AppRole::Client->value);
            const clientHint = document.getElementById('user-client-hint');

            function destroyClientSelect2() {
                if (!clientSelect || typeof window.jQuery === 'undefined') {
                    return;
                }
                const $el = window.jQuery(clientSelect);
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
            }

            function initClientSelect2() {
                if (!clientSelect || typeof window.FormEnhancements === 'undefined') {
                    return;
                }
                clientSelect.classList.remove('no-select2');
                window.FormEnhancements.initSelect2(clientPickerField || clientSelect.parentElement);
            }

            function setClientPickerActive(isEndUser) {
                if (clientPickerField) {
                    clientPickerField.classList.toggle('d-none', !isEndUser);
                }
                if (clientRequiredMark) {
                    clientRequiredMark.classList.toggle('d-none', !isEndUser);
                    clientRequiredMark.setAttribute('aria-hidden', isEndUser ? 'false' : 'true');
                }
                if (clientHint) {
                    clientHint.classList.toggle('d-none', !isEndUser);
                }
                if (!clientSelect) {
                    return;
                }

                destroyClientSelect2();

                if (isEndUser) {
                    clientSelect.classList.remove('no-select2');
                    clientSelect.setAttribute('name', 'client_id');
                    clientSelect.setAttribute('required', 'required');
                    clientSelect.removeAttribute('disabled');
                    clientSelect.removeAttribute('tabindex');
                    clientSelect.removeAttribute('aria-hidden');
                    initClientSelect2();
                } else {
                    clientSelect.classList.add('no-select2');
                    clientSelect.removeAttribute('name');
                    clientSelect.removeAttribute('required');
                    clientSelect.setAttribute('disabled', 'disabled');
                    clientSelect.setAttribute('tabindex', '-1');
                    clientSelect.setAttribute('aria-hidden', 'true');
                    clientSelect.value = '';
                }
            }

            function toggleRoleFields() {
                const role = roleSelect.value;
                const isEndUser = role === endUserRole;
                const showMap = trackableRoles.includes(role);
                const showClientAuto = role === clientRole;

                if (mapField) {
                    mapField.classList.toggle('d-none', !showMap);
                    if (!showMap && mapCheckbox) {
                        mapCheckbox.checked = false;
                    }
                }

                setClientPickerActive(isEndUser);

                if (clientAutoField) {
                    clientAutoField.classList.toggle('d-none', !showClientAuto);
                }
            }

            if (userForm) {
                userForm.addEventListener('submit', function (event) {
                    if (roleSelect.value !== endUserRole) {
                        return;
                    }
                    if (!clientSelect || clientSelect.disabled) {
                        return;
                    }
                    if (clientSelect.value) {
                        return;
                    }
                    event.preventDefault();
                    clientSelect.focus();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: @json(__('app.forms.client_company')),
                            text: @json(__('app.forms.client_picker_hint')),
                        });
                    }
                });
            }

            roleSelect.addEventListener('change', toggleRoleFields);

            if (typeof window.jQuery !== 'undefined') {
                window.jQuery(roleSelect).on('change select2:select', toggleRoleFields);
            }

            if (window.FormEnhancements) {
                window.setTimeout(toggleRoleFields, 0);
            } else {
                toggleRoleFields();
            }

            if (window.CountryCodeSelector && typeof window.CountryCodeSelector.init === 'function') {
                document.querySelectorAll('[data-country-phone-row]').forEach(function (row) {
                    if (row.dataset.countrySelectorInit === '1') {
                        return;
                    }
                    window.CountryCodeSelector.init(row, {
                        countries: window.COUNTRIES_DIAL_CODES || [],
                        defaultCode: row.getAttribute('data-default-country-code') || '',
                        fieldName: row.getAttribute('data-country-field') || 'country_code',
                    });
                });
            }
        });
    </script>
    @endpush
@endif
