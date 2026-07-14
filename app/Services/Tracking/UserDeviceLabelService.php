<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserDeviceLabelService
{
    public function __construct(
        private DeviceMapIconAuthorization $auth,
        private DeviceOdometerService $odometer,
        private DeviceFuelService $fuel,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(User $user, Device $device, array $input): array
    {
        if (! $this->auth->canEditVehicleLabel($user, $device)) {
            throw ValidationException::withMessages([
                'device' => [__('app.common.not_found')],
            ]);
        }

        $validator = Validator::make($input, $this->rules($device));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $vehicleName = isset($data['vehicle_name']) ? trim((string) $data['vehicle_name']) : null;
        $device->vehicle_name = $vehicleName === '' ? null : $vehicleName;
        $device->vehicle_number = Device::normalizeVehicleNumber($data['vehicle_number'] ?? null);
        $device->save();

        if (array_key_exists('odometer_base_km', $data) && $data['odometer_base_km'] !== null && $data['odometer_base_km'] !== '') {
            $km = round((float) $data['odometer_base_km'], 1);
            $previous = $this->odometer->baselineKm($device);
            if ($previous === null || abs($previous - $km) > 0.05) {
                $this->odometer->setBaseline($device, $km);
            }
        }

        $fuelPayload = [];
        foreach ([
            'fuel_consumption_l_per_100km',
            'fuel_efficiency_unit',
            'fuel_tank_capacity_l',
            'fuel_sensor_unit',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $fuelPayload[$key] = $data[$key];
            }
        }
        if ($fuelPayload !== []) {
            $this->fuel->syncSettings($device, $fuelPayload);
        }

        // Live reading uses Traccar positions (same path as admin device form).
        $this->odometer->latestPosition($device);
        $fuelSettings = $this->fuel->settings($device);

        return [
            'vehicle_name' => $device->vehicle_name,
            'vehicle_number' => $device->vehicle_number,
            'odometer_base_km' => $this->odometer->baselineKm($device),
            'odometer_display_km' => $this->odometer->displayKm($device),
            'fuel_consumption_l_per_100km' => $fuelSettings['consumption_l_per_100km'],
            'fuel_efficiency_unit' => $fuelSettings['efficiency_unit'],
            'fuel_tank_capacity_l' => $fuelSettings['tank_capacity_l'],
            'fuel_sensor_unit' => $fuelSettings['sensor_unit'],
            'primary_label' => $device->listPrimaryLabel(),
            'secondary_label' => $device->listSecondaryLabel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Device $device): array
    {
        return [
            'vehicle_name' => 'nullable|string|max:120',
            'vehicle_number' => [
                'nullable',
                'string',
                'max:40',
                function (string $attribute, mixed $value, \Closure $fail) use ($device): void {
                    if (! is_string($value) || Device::normalizeVehicleNumber($value) === null) {
                        return;
                    }

                    if (Device::isVehicleNumberTaken($value, (int) $device->id)) {
                        $fail(__('validation.unique', ['attribute' => __('app.forms.vehicle_number')]));
                    }
                },
            ],
            'odometer_base_km' => 'nullable|numeric|min:0|max:9999999',
            'fuel_consumption_l_per_100km' => 'nullable|numeric|min:0.1|max:100',
            'fuel_efficiency_unit' => 'nullable|in:l_per_100km,km_per_l',
            'fuel_tank_capacity_l' => 'nullable|numeric|min:1|max:2000',
            'fuel_sensor_unit' => 'nullable|in:liters,percent',
        ];
    }
}
