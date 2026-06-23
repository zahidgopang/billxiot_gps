@php
    use App\Models\Device;

    $device = $device ?? null;
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $selectedClient = old('client_id', $formClientId ?? (isset($device) ? app(\App\Services\Authorization\TenantScopeService::class)->clientIdForDevice($device) : null));
    $selectedUserId = old('user_id', optional($device)->user_id ?? '');
    $usersByClient = $usersByClient ?? [];
    $deviceType = old('device_type', optional($device)->device_type ?? '');
    $simType = old('sim_type', optional($device)->sim_type ?? '');
    $simNumber = old('sim_number', optional($device)->sim_number ?? '');
    $vehicleName = old('vehicle_name', optional($device)->vehicle_name ?? '');
    $vehicleNumber = old('vehicle_number', optional($device)->vehicle_number ?? '');
    $vehicleModel = old('vehicle_model', optional($device)->vehicle_model ?? '');
    $vehicleType = old('vehicle_type', optional($device)->vehicle_type ?? '');
    $plateType = old('plate_type', optional($device)->plate_type ?? '');
    $allowedDeviceTypes = $allowedDeviceTypes ?? array_keys(Device::DEVICE_TYPES);
    $formClientId = $formClientId ?? ($panel === 'client' ? $selectedClient : ($selectedClient ?: null));
    $needsClient = $panel === 'admin' && ! $formClientId;
    $noClientStock = $formClientId && count($allowedDeviceTypes) === 0;
    $clientStockBalance = $clientStockBalance ?? null;
@endphp

<x-admin.form-section
    :title="__('app.forms.assignment')"
    icon="fas fa-link"
    :description="__('app.forms.assignment_device_hint')"
>
    @if($panel === 'admin' && !empty($clients) && $clients->count())
        <x-admin.form-col>
            <label class="admin-label" for="device-client-id">{{ __('app.forms.client_company') }} <span class="text-danger">*</span></label>
            <select name="client_id" id="device-client-id" class="form-select form-select-sm" required data-placeholder="{{ __('app.forms.select_client') }}">
                <option value="" disabled @selected(! $selectedClient)>{{ __('app.forms.select_client') }}</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) $selectedClient === (string) $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            @error('client_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        </x-admin.form-col>
    @endif

    <x-admin.form-col :full="true">
        @include('admin.partials.client-stock-balance', [
            'balance' => $clientStockBalance,
            'showSelectClientHint' => $needsClient,
        ])
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="device-user-id">{{ __('app.forms.assign_user') }} <span class="text-danger">*</span></label>
        <select name="user_id" id="device-user-id"
            class="form-select form-select-sm"
            required
            data-placeholder="{{ __('app.forms.assign_user') }}">
            <option value="">{{ $panel === 'admin' ? __('app.forms.select_client_first') : __('app.forms.select_user') }}</option>
            @if($panel === 'client')
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected((string) $selectedUserId === (string) $u->id)>
                        {{ $u->name }} ({{ $u->email }})
                    </option>
                @endforeach
            @elseif($selectedClient && !empty($usersByClient[(string) $selectedClient]))
                @foreach($usersByClient[(string) $selectedClient] as $u)
                    <option value="{{ $u['id'] }}" @selected((string) $selectedUserId === (string) $u['id'])>{{ $u['text'] }}</option>
                @endforeach
            @endif
        </select>
        @if($panel === 'admin')
            <p class="admin-hint">{{ __('app.forms.device_client_first_hint') }}</p>
        @endif
        @error('user_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.forms.device_information')"
    icon="fas fa-microchip"
    :description="__('app.forms.device_information_tracker_hint')"
>
    <x-admin.form-col>
        <label class="admin-label" for="device-imei">{{ __('app.admin.devices.imei') }} <span class="text-danger">*</span></label>
        <input type="text" name="imei" id="device-imei" value="{{ old('imei', optional($device)->imei ?? '') }}" class="form-control form-control-sm admin-ltr" dir="ltr" required>
        <p class="admin-hint">{{ __('app.forms.device_imei_hint') }}</p>
        @error('imei') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="device-name">{{ __('app.forms.device_label') }}</label>
        <input type="text" name="name" id="device-name" value="{{ old('name', optional($device)->name ?? '') }}" class="form-control form-control-sm" placeholder="{{ __('app.forms.device_label_placeholder') }}">
        <p class="admin-hint">{{ __('app.forms.device_label_hint') }}</p>
        @error('name') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="device-type">{{ __('app.forms.device_type') }} <span class="text-danger">*</span></label>
        <select name="device_type" id="device-type" class="form-select form-select-sm"
            @if($needsClient || $noClientStock) disabled @else required @endif>
            @if($needsClient)
                <option value="" disabled selected>{{ __('app.forms.select_client_first') }}</option>
            @elseif($noClientStock)
                <option value="" disabled selected>{{ __('app.admin.stock_sales.no_client_stock') }}</option>
            @else
                <option value="" disabled @selected($deviceType === '')>{{ __('app.forms.select_device_type') }}</option>
                @foreach($allowedDeviceTypes as $typeKey)
                    <option value="{{ $typeKey }}" @selected($deviceType === $typeKey)>
                        {{ __('app.forms.device_type_' . $typeKey) }}
                    </option>
                @endforeach
            @endif
        </select>
        <p class="admin-hint">{{ __('app.forms.device_type_stock_hint') }}</p>
        @error('device_type') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="device-sim-type">{{ __('app.forms.sim_type') }}</label>
        <select name="sim_type" id="device-sim-type" class="form-select form-select-sm" data-search="false">
            <option value="">{{ __('app.forms.select_sim_type') }}</option>
            @foreach(Device::SIM_TYPES as $typeKey => $typeLabel)
                <option value="{{ $typeKey }}" @selected($simType === $typeKey)>
                    {{ __('app.forms.sim_type_' . $typeKey) }}
                </option>
            @endforeach
        </select>
        @error('sim_type') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="device-sim-number">{{ __('app.forms.sim_number') }}</label>
        <input type="text" name="sim_number" id="device-sim-number" value="{{ old('sim_number', $simNumber) }}" class="form-control form-control-sm admin-ltr" dir="ltr" maxlength="40" placeholder="{{ __('app.forms.sim_number_placeholder') }}">
        @error('sim_number') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="device-status">{{ __('app.common.status') }}</label>
        <select name="status" id="device-status" class="form-select form-select-sm" required>
            @php $status = old('status', optional($device)->status ?? 'active'); @endphp
            <option value="active" @selected($status === 'active')>{{ __('app.common.active') }}</option>
            <option value="inactive" @selected($status === 'inactive')>{{ __('app.common.inactive') }}</option>
            <option value="blocked" @selected($status === 'blocked')>{{ __('app.common.blocked') }}</option>
        </select>
        @error('status') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.forms.vehicle_information')"
    icon="fas fa-car"
    :description="__('app.forms.vehicle_information_hint')"
>
    <x-admin.form-col>
        <label class="admin-label" for="vehicle-name">{{ __('app.forms.vehicle_name') }}</label>
        <input type="text" name="vehicle_name" id="vehicle-name" value="{{ $vehicleName }}" class="form-control form-control-sm" placeholder="{{ __('app.forms.vehicle_name_placeholder') }}">
        <p class="admin-hint">{{ __('app.forms.vehicle_name_hint') }}</p>
        @error('vehicle_name') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="vehicle-number">{{ __('app.forms.vehicle_number') }}</label>
        <input type="text" name="vehicle_number" id="vehicle-number" value="{{ $vehicleNumber }}" class="form-control form-control-sm admin-ltr" dir="ltr" placeholder="{{ __('app.forms.vehicle_number_placeholder') }}">
        <p class="admin-hint">{{ __('app.forms.vehicle_number_hint') }}</p>
        @error('vehicle_number') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="vehicle-model">{{ __('app.forms.vehicle_model') }}</label>
        <input type="text" name="vehicle_model" id="vehicle-model" value="{{ $vehicleModel }}" class="form-control form-control-sm" placeholder="{{ __('app.forms.vehicle_model_placeholder') }}">
        @error('vehicle_model') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="vehicle-type">{{ __('app.forms.vehicle_type') }}</label>
        <select name="vehicle_type" id="vehicle-type" class="form-select form-select-sm">
            <option value="">{{ __('app.forms.select_vehicle_type') }}</option>
            @foreach(Device::VEHICLE_TYPES as $typeKey => $typeLabel)
                <option value="{{ $typeKey }}" @selected($vehicleType === $typeKey)>
                    {{ __('app.forms.vehicle_type_' . $typeKey) }}
                </option>
            @endforeach
        </select>
        <p class="admin-hint">{{ __('app.forms.vehicle_type_hint') }}</p>
        @error('vehicle_type') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="vehicle-plate-type">{{ __('app.forms.plate_type') }}</label>
        <select name="plate_type" id="vehicle-plate-type" class="form-select form-select-sm" data-search="false">
            <option value="">{{ __('app.forms.select_plate_type') }}</option>
            @foreach(Device::PLATE_TYPES as $typeKey => $typeLabel)
                <option value="{{ $typeKey }}" @selected($plateType === $typeKey)>
                    {{ __('app.forms.plate_type_' . $typeKey) }}
                </option>
            @endforeach
        </select>
        @error('plate_type') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>
