<?php

namespace App\Services\Tracking;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

/**
 * Resolve Traccar wire type + attributes for immobilizer / device commands
 * based on GPS model / protocol profile (e.g. Teltonika setdigout).
 */
class CommandProtocolMapper
{
    /**
     * @return array{
     *   profile: string,
     *   type: string,
     *   attributes: array<string, mixed>,
     *   wire_data: string
     * }
     */
    public function resolve(Device $device, string $logicalType, string $rawData = ''): array
    {
        $logicalType = trim($logicalType);
        $profile = $this->detectProfile($device);
        $profiles = config('device_commands.profiles', []);

        // custom / non-immobilizer: pass through
        if ($logicalType === 'custom') {
            return [
                'profile' => $profile,
                'type' => 'custom',
                'attributes' => ['data' => $rawData],
                'wire_data' => $rawData,
            ];
        }

        $map = $profiles[$profile][$logicalType]
            ?? $profiles['generic'][$logicalType]
            ?? null;

        if ($map === null) {
            // alarmArm, rebootDevice, etc. — native Traccar type
            return [
                'profile' => $profile,
                'type' => $logicalType,
                'attributes' => $rawData !== '' ? ['data' => $rawData] : [],
                'wire_data' => $rawData,
            ];
        }

        $type = (string) ($map['type'] ?? $logicalType);
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($map['attributes'] ?? null) ? $map['attributes'] : [];
        $wireData = (string) ($attributes['data'] ?? $rawData);

        return [
            'profile' => $profile,
            'type' => $type,
            'attributes' => $attributes,
            'wire_data' => $wireData,
        ];
    }

    public function detectProfile(Device $device): string
    {
        $attrs = $this->deviceAttributes($device);
        $explicit = strtolower(trim((string) (
            $attrs['command_profile']
            ?? $attrs['protocol']
            ?? $device->getAttribute('model')
            ?? ''
        )));

        $haystack = strtolower(implode(' ', array_filter([
            $explicit,
            (string) ($device->getAttribute('model') ?? ''),
            (string) ($device->getAttribute('name') ?? ''),
            (string) ($attrs['device_model'] ?? ''),
            (string) ($attrs['protocol'] ?? ''),
        ])));

        $profiles = config('device_commands.profiles', []);
        foreach ($profiles as $name => $cfg) {
            if ($name === 'generic') {
                continue;
            }
            $needles = $cfg['match'] ?? [];
            if (! is_array($needles)) {
                continue;
            }
            foreach ($needles as $needle) {
                $needle = strtolower((string) $needle);
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return $name;
                }
            }
        }

        return (string) config('device_commands.default_profile', 'generic');
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceAttributes(Device $device): array
    {
        $raw = $device->getAttribute('attributes');
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        // Unified id — read fresh from tc_devices if model attrs empty.
        try {
            $row = DB::table(config('traccar.tables.devices', 'tc_devices'))
                ->where('id', $device->id)
                ->value('attributes');
            if (is_string($row) && $row !== '') {
                $decoded = json_decode($row, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return [];
    }
}
