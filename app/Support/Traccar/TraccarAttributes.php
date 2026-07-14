<?php

namespace App\Support\Traccar;

final class TraccarAttributes
{
    public static function encode(array $attributes): string
    {
        return json_encode($attributes, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPositionPayload(array $payload): array
    {
        $attrs = [];

        foreach ([
            'battery' => 'battery',
            'battery_level' => 'battery',
            'ignition' => 'ignition',
            'acc' => 'acc',
            'gsm_signal' => 'gsm',
            'gps_signal' => 'gps',
            'satellites' => 'sat',
            'odometer' => 'odometer',
            'fuel' => 'fuel',
            'fuel_level' => 'fuel',
            'power_cut' => 'powerCut',
            'panic' => 'alarm',
            'gps_fix' => 'gpsFix',
        ] as $key => $attrKey) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null) {
                continue;
            }

            $value = $payload[$key];

            if ($key === 'panic' && $value) {
                $attrs['alarm'] = 'sos';
            } else {
                $attrs[$attrKey] = $value;
            }
        }

        return $attrs;
    }

    public static function toPositionFields(array $attributes): array
    {
        return [
            'battery_level' => $attributes['battery'] ?? $attributes['batteryLevel'] ?? null,
            'ignition' => (bool) ($attributes['ignition'] ?? false),
            'acc' => (bool) ($attributes['acc'] ?? false),
            'gsm_signal' => $attributes['gsm']
                ?? $attributes['gsmSignal']
                ?? $attributes['rssi']
                ?? $attributes['signal']
                ?? $attributes['signalStrength']
                ?? $attributes['cellSignal']
                ?? null,
            'gps_signal' => $attributes['gps']
                ?? $attributes['gpsSignal']
                ?? $attributes['hdop']
                ?? null,
            'satellites' => $attributes['sat']
                ?? $attributes['satellites']
                ?? $attributes['satellite']
                ?? $attributes['satInView']
                ?? $attributes['satVisible']
                ?? null,
            'odometer' => $attributes['odometer'] ?? null,
            'fuel' => self::firstNumeric($attributes, [
                'fuel',
                'fuelLevel',
                'fuel_level',
                'fuelLiters',
                'io9',
                'io19',
                'canFuel',
                'fuel1',
            ]),
            'power_cut' => (bool) ($attributes['powerCut'] ?? false),
            'panic' => ($attributes['alarm'] ?? null) === 'sos',
            'gps_fix' => $attributes['gpsFix'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $keys
     */
    private static function firstNumeric(array $attributes, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $attributes) || $attributes[$key] === null || $attributes[$key] === '') {
                continue;
            }
            if (is_numeric($attributes[$key])) {
                return $attributes[$key];
            }
        }

        return null;
    }
}
