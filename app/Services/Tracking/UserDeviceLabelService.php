<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserDeviceLabelService
{
    public function __construct(private DeviceMapIconAuthorization $auth) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{vehicle_name: ?string, vehicle_number: ?string, primary_label: string, secondary_label: ?string}
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

        return [
            'vehicle_name' => $device->vehicle_name,
            'vehicle_number' => $device->vehicle_number,
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
        ];
    }
}
