@php
    use App\Models\Device;

    $device = $device ?? null;
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $selectedClient = old('client_id', $formClientId ?? (isset($device) ? app(\App\Services\Authorization\TenantScopeService::class)->clientIdForDevice($device) : null));
    $selectedUserIds = collect(old('user_ids', isset($device) ? $device->user_ids : []))
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->values()
        ->all();
    $usersByClient = $usersByClient ?? [];
    $deviceType = old('device_type', optional($device)->device_type ?? '');
    $simType = old('sim_type', optional($device)->sim_type ?? '');
    $simNumber = old('sim_number', optional($device)->sim_number ?? '');
    $vehicleName = old('vehicle_name', optional($device)->vehicle_name ?? '');
    $vehicleNumber = old('vehicle_number', optional($device)->vehicle_number ?? '');
    $vehicleModel = old('vehicle_model', optional($device)->vehicle_model ?? '');
    $vehicleType = old('vehicle_type', optional($device)->vehicle_type ?? '');
    if ($vehicleType !== '' && ! isset(Device::VEHICLE_TYPES[$vehicleType])) {
        $vehicleType = Device::guessBodyTypeFromMapIconKey($vehicleType) ?? '';
    }
    $driverName = old('driver_name', optional($device)->driver_name ?? '');
    $driverContact = old('driver_contact', optional($device)->driverContactNumber() ?? '');
    $plateType = old('plate_type', optional($device)->plate_type ?? '');
    $odometerBaseKm = old('odometer_base_km', optional($device)->odometerBaselineKm());
    $odometerDisplayKm = isset($device) ? $device->odometerDisplayKm() : null;
    $fuelRate = old('fuel_consumption_l_per_100km', optional($device)->fuelConsumptionLPer100km());
    $fuelUnit = old('fuel_efficiency_unit', optional($device)->fuelEfficiencyUnit() ?? 'l_per_100km');
    $fuelTank = old('fuel_tank_capacity_l', optional($device)->fuelTankCapacityL());
    $fuelSensorUnit = old('fuel_sensor_unit', optional($device)->fuelSensorUnit() ?? 'liters');
    $allowedDeviceTypes = $allowedDeviceTypes ?? array_keys(Device::DEVICE_TYPES);
    $formClientId = $formClientId ?? ($panel === 'client' ? $selectedClient : ($selectedClient ?: null));
    $needsClient = $panel === 'admin' && ! $formClientId;
    $noClientStock = $formClientId && count($allowedDeviceTypes) === 0;
    $clientStockBalance = $clientStockBalance ?? null;
    $activeRoutes = $activeRoutes ?? collect();
    $assignedRouteId = old('route_id', $assignedRouteId ?? null);
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
        <label class="admin-label" for="device-user-id">{{ __('app.forms.assign_users') }} <span class="text-danger">*</span></label>
        <select name="user_ids[]" id="device-user-id"
            class="form-select form-select-sm"
            multiple
            required
            data-placeholder="{{ __('app.forms.assign_users') }}">
            @if($panel === 'client')
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(in_array((string) $u->id, $selectedUserIds, true))>
                        {{ $u->name }} ({{ $u->email }})
                    </option>
                @endforeach
            @elseif($selectedClient && !empty($usersByClient[(string) $selectedClient]))
                @foreach($usersByClient[(string) $selectedClient] as $u)
                    <option value="{{ $u['id'] }}" @selected(in_array((string) $u['id'], $selectedUserIds, true))>{{ $u['text'] }}</option>
                @endforeach
            @endif
        </select>
        <p class="admin-hint">{{ $panel === 'admin' ? __('app.forms.device_client_first_hint') . ' ' . __('app.forms.assign_users_hint') : __('app.forms.assign_users_hint') }}</p>
        @error('user_ids') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        @error('user_ids.*') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
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
        <label class="admin-label" for="assigned-route">{{ __('app.routes.assigned_route') }}</label>
        <select name="route_id" id="assigned-route" class="form-select form-select-sm">
            <option value="">{{ __('app.routes.no_route') }}</option>
            @foreach($activeRoutes as $routeOption)
                <option value="{{ $routeOption->id }}" @selected((string) $assignedRouteId === (string) $routeOption->id)>
                    {{ $routeOption->name }} ({{ $routeOption->start_city }} ? {{ $routeOption->destination_city }})
                </option>
            @endforeach
        </select>
        <p class="admin-hint">{{ __('app.routes.assigned_route_hint') }}</p>
        @error('route_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="odometer-base-km">{{ __('app.odometer.current_reading') }}</label>
        <input type="number" name="odometer_base_km" id="odometer-base-km" step="0.1" min="0"
               value="{{ $odometerBaseKm !== null ? $odometerBaseKm : '' }}"
               class="form-control form-control-sm admin-ltr" dir="ltr"
               placeholder="{{ __('app.odometer.placeholder') }}">
        <p class="admin-hint">{{ __('app.odometer.hint') }}</p>
        @if($odometerDisplayKm !== null)
            <p class="admin-hint mb-0">
                {{ __('app.odometer.live_reading') }}:
                <strong>{{ number_format($odometerDisplayKm, 1) }} km</strong>
            </p>
        @endif
        @error('odometer_base_km') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="fuel-consumption-rate">{{ __('app.fuel.consumption_rate') }}</label>
        <input type="number" name="fuel_consumption_l_per_100km" id="fuel-consumption-rate" step="0.1" min="0.1" max="100"
               value="{{ $fuelRate !== null ? $fuelRate : '' }}"
               class="form-control form-control-sm admin-ltr" dir="ltr"
               placeholder="{{ __('app.fuel.consumption_placeholder') }}">
        <p class="admin-hint">{{ __('app.fuel.consumption_hint') }}</p>
        @error('fuel_consumption_l_per_100km') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="fuel-efficiency-unit">{{ __('app.fuel.efficiency_unit') }}</label>
        <select name="fuel_efficiency_unit" id="fuel-efficiency-unit" class="form-select form-select-sm" data-search="false">
            <option value="l_per_100km" @selected($fuelUnit === 'l_per_100km')>{{ __('app.fuel.unit_l_100') }}</option>
            <option value="km_per_l" @selected($fuelUnit === 'km_per_l')>{{ __('app.fuel.unit_km_l') }}</option>
        </select>
        @error('fuel_efficiency_unit') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="fuel-tank-capacity">{{ __('app.fuel.tank_capacity') }}</label>
        <input type="number" name="fuel_tank_capacity_l" id="fuel-tank-capacity" step="0.1" min="1" max="2000"
               value="{{ $fuelTank !== null ? $fuelTank : '' }}"
               class="form-control form-control-sm admin-ltr" dir="ltr"
               placeholder="{{ __('app.fuel.tank_placeholder') }}">
        <p class="admin-hint">{{ __('app.fuel.tank_hint') }}</p>
        @error('fuel_tank_capacity_l') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="fuel-sensor-unit">{{ __('app.fuel.sensor_unit') }}</label>
        <select name="fuel_sensor_unit" id="fuel-sensor-unit" class="form-select form-select-sm" data-search="false">
            <option value="liters" @selected($fuelSensorUnit === 'liters')>{{ __('app.fuel.sensor_liters') }}</option>
            <option value="percent" @selected($fuelSensorUnit === 'percent')>{{ __('app.fuel.sensor_percent') }}</option>
        </select>
        @error('fuel_sensor_unit') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
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

<x-admin.form-section
    :title="__('app.forms.driver_information')"
    icon="fas fa-id-card"
    :description="__('app.forms.driver_information_hint')"
>
    <x-admin.form-col>
        <label class="admin-label" for="driver-name">{{ __('app.forms.driver_name') }}</label>
        <input type="text" name="driver_name" id="driver-name" value="{{ $driverName }}" class="form-control form-control-sm" maxlength="120" placeholder="{{ __('app.forms.driver_name_placeholder') }}">
        <p class="admin-hint">{{ __('app.forms.driver_name_hint') }}</p>
        @error('driver_name') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="driver-contact">{{ __('app.forms.driver_contact') }}</label>
        <input type="tel" name="driver_contact" id="driver-contact" value="{{ $driverContact }}" class="form-control form-control-sm admin-ltr" dir="ltr" maxlength="40" placeholder="{{ __('app.forms.driver_contact_placeholder') }}">
        <p class="admin-hint">{{ __('app.forms.driver_contact_hint') }}</p>
        @error('driver_contact') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>
