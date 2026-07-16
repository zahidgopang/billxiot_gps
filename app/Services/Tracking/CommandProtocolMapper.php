<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Support\Traccar\TraccarAppFields;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Resolve Traccar wire type + attributes for immobilizer / device commands
 * based on GPS protocol profile (e.g. Teltonika setdigout).
 *
 * Protocol is preferred from Traccar's live data (tc_positions.protocol), not
 * only from optional BillX fields that are often empty for existing devices.
 */
class CommandProtocolMapper
{
    /**
     * @return array{
     *   profile: string,
     *   type: string,
     *   attributes: array<string, mixed>,
     *   wire_data: string,
     *   protocol?: string|null,
     *   source?: string|null
     * }
     */
    public function resolve(Device $device, string $logicalType, string $rawData = ''): array
    {
        $logicalType = trim($logicalType);
        $detected = $this->detectProfileDetailed($device);
        $profile = $detected['profile'];
        $profiles = config('device_commands.profiles', []);

        // custom / non-immobilizer: pass through
        if ($logicalType === 'custom') {
            return [
                'profile' => $profile,
                'type' => 'custom',
                'attributes' => ['data' => $rawData],
                'wire_data' => $rawData,
                'protocol' => $detected['protocol'],
                'source' => $detected['source'],
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
                'protocol' => $detected['protocol'],
                'source' => $detected['source'],
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
            'protocol' => $detected['protocol'],
            'source' => $detected['source'],
        ];
    }

    public function detectProfile(Device $device): string
    {
        return $this->detectProfileDetailed($device)['profile'];
    }

    /**
     * @return array{profile: string, protocol: string|null, source: string}
     */
    public function detectProfileDetailed(Device $device): array
    {
        $attrs = $this->deviceAttributes($device);
        $default = (string) config('device_commands.default_profile', 'generic');

        // 1) Explicit BillX command profile override.
        $explicitProfile = $this->normalizeToken($attrs['command_profile'] ?? null);
        if ($explicitProfile !== null && $this->isKnownProfile($explicitProfile)) {
            return [
                'profile' => $explicitProfile,
                'protocol' => $this->normalizeToken(
                    $attrs[TraccarAppFields::KEY_TRACCAR_PROTOCOL]
                        ?? $attrs['protocol']
                        ?? null
                ),
                'source' => 'command_profile',
            ];
        }

        // 2) Explicit / cached protocol on the device record.
        $cachedProtocol = $this->normalizeToken(
            $attrs['protocol']
                ?? $attrs[TraccarAppFields::KEY_TRACCAR_PROTOCOL]
                ?? null
        );
        if ($cachedProtocol !== null) {
            $profile = $this->profileForProtocol($cachedProtocol);
            if ($profile !== null) {
                return [
                    'profile' => $profile,
                    'protocol' => $cachedProtocol,
                    'source' => isset($attrs['protocol']) ? 'device_protocol' : 'cached_protocol',
                ];
            }
        }

        // 3) Live protocol from Traccar positions (source of truth).
        $liveProtocol = $this->latestPositionProtocol($device);
        if ($liveProtocol !== null) {
            $this->rememberProtocol($device, $liveProtocol, $attrs);
            $profile = $this->profileForProtocol($liveProtocol);
            if ($profile !== null) {
                return [
                    'profile' => $profile,
                    'protocol' => $liveProtocol,
                    'source' => 'position_protocol',
                ];
            }
        }

        // 4) Hardware model fields (not vehicle display name).
        $modelHaystack = $this->buildHaystack([
            $device->getAttribute('model'),
            $attrs['device_model'] ?? null,
            $attrs['protocol'] ?? null,
            $attrs[TraccarAppFields::KEY_TRACCAR_PROTOCOL] ?? null,
            $explicitProfile,
        ]);
        $profile = $this->matchHaystack($modelHaystack);
        if ($profile !== null) {
            return [
                'profile' => $profile,
                'protocol' => $liveProtocol ?? $cachedProtocol,
                'source' => 'hardware_model',
            ];
        }

        // 5) Legacy keyword fallback including device name.
        $fullHaystack = $this->buildHaystack([
            $modelHaystack,
            $device->getAttribute('name'),
        ]);
        $profile = $this->matchHaystack($fullHaystack);
        if ($profile !== null) {
            return [
                'profile' => $profile,
                'protocol' => $liveProtocol ?? $cachedProtocol,
                'source' => 'keyword_fallback',
            ];
        }

        return [
            'profile' => $default,
            'protocol' => $liveProtocol ?? $cachedProtocol,
            'source' => 'default',
        ];
    }

    /**
     * Persist the observed Traccar protocol onto tc_devices.attributes so
     * subsequent command mapping does not depend only on position history.
     */
    public function rememberProtocol(Device $device, string $protocol, ?array $attrs = null): void
    {
        $protocol = $this->normalizeToken($protocol);
        if ($protocol === null) {
            return;
        }

        $attrs ??= $this->deviceAttributes($device);
        $current = $this->normalizeToken(
            $attrs[TraccarAppFields::KEY_TRACCAR_PROTOCOL] ?? $attrs['protocol'] ?? null
        );
        if ($current === $protocol) {
            return;
        }

        try {
            $raw = $device->getAttribute('attributes');
            if (is_array($raw)) {
                $json = json_encode($raw, JSON_UNESCAPED_UNICODE) ?: '{}';
            } elseif (is_string($raw) && $raw !== '') {
                $json = $raw;
            } else {
                $json = '{}';
            }

            $merged = TraccarAppFields::mergeInto($json, [
                TraccarAppFields::KEY_TRACCAR_PROTOCOL => $protocol,
            ]);

            DB::table(config('traccar.tables.devices', 'tc_devices'))
                ->where('id', (int) $device->id)
                ->update(['attributes' => $merged]);

            $device->setAttribute('attributes', $merged);
        } catch (\Throwable $e) {
            Log::debug('command.protocol_cache_failed', [
                'device_id' => $device->id,
                'protocol' => $protocol,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function profileForProtocol(?string $protocol): ?string
    {
        $protocol = $this->normalizeToken($protocol);
        if ($protocol === null) {
            return null;
        }

        $map = config('device_commands.protocol_to_profile', []);
        if (is_array($map) && isset($map[$protocol])) {
            $mapped = $this->normalizeToken($map[$protocol]);
            if ($mapped !== null && $this->isKnownProfile($mapped)) {
                return $mapped;
            }
        }

        // Same-name profile (e.g. protocol "teltonika" + profile "teltonika").
        if ($this->isKnownProfile($protocol) && $protocol !== 'generic') {
            return $protocol;
        }

        return $this->matchHaystack($protocol);
    }

    protected function latestPositionProtocol(Device $device): ?string
    {
        $positionsTable = config('traccar.tables.positions', 'tc_positions');

        try {
            $positionId = (int) ($device->getAttribute('positionid') ?? 0);
            if ($positionId > 0) {
                $protocol = DB::table($positionsTable)
                    ->where('id', $positionId)
                    ->value('protocol');
                $normalized = $this->normalizeToken($protocol);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            $protocol = DB::table($positionsTable)
                ->where('deviceid', (int) $device->id)
                ->orderByDesc('id')
                ->value('protocol');

            return $this->normalizeToken($protocol);
        } catch (\Throwable) {
            return null;
        }
    }

    private function matchHaystack(string $haystack): ?string
    {
        $haystack = strtolower(trim($haystack));
        if ($haystack === '') {
            return null;
        }

        $profiles = config('device_commands.profiles', []);
        foreach ($profiles as $name => $cfg) {
            if ($name === 'generic' || ! is_array($cfg)) {
                continue;
            }
            $needles = $cfg['match'] ?? [];
            if (! is_array($needles)) {
                continue;
            }
            foreach ($needles as $needle) {
                $needle = strtolower(trim((string) $needle));
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return (string) $name;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $parts
     */
    private function buildHaystack(array $parts): string
    {
        $tokens = [];
        foreach ($parts as $part) {
            if ($part === null) {
                continue;
            }
            $value = strtolower(trim((string) $part));
            if ($value !== '') {
                $tokens[] = $value;
            }
        }

        return implode(' ', $tokens);
    }

    private function isKnownProfile(string $profile): bool
    {
        $profiles = config('device_commands.profiles', []);

        return is_array($profiles) && array_key_exists($profile, $profiles);
    }

    private function normalizeToken(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $token = strtolower(trim((string) $value));

        return $token !== '' ? $token : null;
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
