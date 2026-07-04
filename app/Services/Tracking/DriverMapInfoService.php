<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Driver details for map overlays (name, contact, email, photo).
 */
class DriverMapInfoService
{
    /**
     * @param  Collection<int, Device>  $devices
     * @return array<int, array<string, mixed>|null>
     */
    public function payloadsForDevices(Collection $devices): array
    {
        if ($devices->isEmpty()) {
            return [];
        }

        $driverRows = $this->assignedDriverRowsByDeviceId($devices->pluck('id')->all());
        $out = [];

        foreach ($devices as $device) {
            $out[$device->id] = $this->payloadForDevice($device, $driverRows[$device->id] ?? null);
        }

        return $out;
    }

    /**
     * @return array{name: string, phone: ?string, phone_tel: ?string, email: ?string, photo: ?string}|null
     */
    public function payloadForDevice(Device $device, ?object $driverRow = null): ?array
    {
        $name = null;
        $phone = null;
        $email = null;
        $photo = null;

        if ($driverRow !== null) {
            $attrs = json_decode((string) ($driverRow->attributes ?? '{}'), true) ?: [];
            $name = trim((string) ($driverRow->name ?? ''));
            $phone = $this->normalizePhone($attrs['phone'] ?? $attrs['contact'] ?? $attrs['mobile'] ?? '');
            $email = $this->normalizeEmail($attrs['email'] ?? $attrs['mail'] ?? '');
            $photo = $this->normalizePhotoUrl($attrs['photo'] ?? $attrs['avatar'] ?? $attrs['image'] ?? '');
        }

        if ($name === '') {
            $name = $device->driverDisplayName() ?? '';
        }
        if ($phone === null) {
            $phone = $device->driverContactNumber();
        }

        $name = trim($name);
        if ($name === '' && $phone === null && $email === null) {
            return null;
        }

        return [
            'name' => $name !== '' ? $name : '—',
            'phone' => $phone,
            'phone_tel' => $phone !== null ? $this->phoneToTel($phone) : null,
            'email' => $email,
            'photo' => $photo,
        ];
    }

    /**
     * @param  list<int>  $deviceIds
     * @return array<int, object>
     */
    private function assignedDriverRowsByDeviceId(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_map('intval', $deviceIds)));
        if ($deviceIds === []) {
            return [];
        }

        $driversTable = config('traccar.tables.drivers', 'tc_drivers');
        if (! TraccarSchema::hasTable($driversTable) || ! TraccarSchema::hasTable('tc_device_driver')) {
            return [];
        }

        return DB::table('tc_device_driver as dd')
            ->join($driversTable.' as d', 'd.id', '=', 'dd.driverid')
            ->whereIn('dd.deviceid', $deviceIds)
            ->get(['dd.deviceid', 'd.name', 'd.attributes'])
            ->keyBy(fn ($row) => (int) $row->deviceid)
            ->all();
    }

    private function normalizePhone(mixed $value): ?string
    {
        $phone = trim((string) $value);

        return $phone !== '' ? $phone : null;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $email = trim((string) $value);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function normalizePhotoUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) {
            return $url;
        }

        return null;
    }

    private function phoneToTel(string $phone): ?string
    {
        $tel = preg_replace('/[^\d+]/', '', $phone);
        $tel = preg_replace('/(?!^)\+/', '', (string) $tel);

        return $tel === '' ? null : $tel;
    }
}
