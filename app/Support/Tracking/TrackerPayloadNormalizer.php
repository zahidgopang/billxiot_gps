<?php

namespace App\Support\Tracking;

/**
 * Maps common GPS tracker / gateway JSON shapes into the flat fields used by DeviceDataController.
 */
final class TrackerPayloadNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalize(array $payload): array
    {
        $attrs = data_get($payload, 'attributes', []);
        $attrs = is_array($attrs) ? $attrs : [];

        return array_filter([
            'imei' => self::string(
                $payload['imei']
                ?? $payload['uniqueId']
                ?? $payload['uniqueid']
                ?? $payload['device_id']
                ?? data_get($payload, 'device.id')
                ?? data_get($payload, 'device.imei')
                ?? data_get($payload, 'device.uniqueId')
            ),
            'timestamp' => self::string(
                $payload['timestamp']
                ?? $payload['fixTime']
                ?? $payload['fixtime']
                ?? $payload['deviceTime']
                ?? $payload['devicetime']
                ?? $payload['recorded_at']
                ?? data_get($payload, 'device.timestamp')
            ),
            'lat' => self::float(
                $payload['lat']
                ?? $payload['latitude']
                ?? data_get($payload, 'device.gps.latitude')
                ?? data_get($payload, 'position.latitude')
            ),
            'lng' => self::float(
                $payload['lng']
                ?? $payload['lon']
                ?? $payload['longitude']
                ?? data_get($payload, 'device.gps.longitude')
                ?? data_get($payload, 'position.longitude')
            ),
            'speed' => self::float(
                $payload['speed']
                ?? $payload['speed_kmh']
                ?? data_get($payload, 'device.gps.speed_kmh')
                ?? data_get($payload, 'device.gps.speed')
                ?? data_get($attrs, 'speed')
            ),
            'heading' => self::float(
                $payload['heading']
                ?? $payload['course']
                ?? $payload['bearing']
                ?? data_get($payload, 'device.gps.heading')
                ?? data_get($attrs, 'course')
            ),
            'battery' => self::int(
                $payload['battery']
                ?? $payload['battery_level']
                ?? data_get($payload, 'device.status.battery')
                ?? data_get($attrs, 'battery')
                ?? data_get($attrs, 'batteryLevel')
            ),
            'ignition' => self::bool(
                $payload['ignition']
                ?? data_get($attrs, 'ignition')
                ?? (isset($payload['acc']) ? $payload['acc'] : null)
            ),
            'acc' => self::bool(
                $payload['acc'] ?? data_get($attrs, 'acc')
            ),
            'gsm_signal' => self::int(
                $payload['gsm_signal']
                ?? $payload['gsm']
                ?? $payload['rssi']
                ?? $payload['signal']
                ?? data_get($attrs, 'gsm')
                ?? data_get($attrs, 'rssi')
            ),
            'gps_signal' => self::int(
                $payload['gps_signal']
                ?? $payload['hdop']
                ?? data_get($attrs, 'hdop')
            ),
            'satellites' => self::int(
                $payload['satellites']
                ?? $payload['sat']
                ?? $payload['sats']
                ?? data_get($attrs, 'sat')
                ?? data_get($attrs, 'satellites')
            ),
            'odometer' => self::int(
                $payload['odometer']
                ?? data_get($attrs, 'odometer')
                ?? data_get($attrs, 'totalDistance')
            ),
            'power_cut' => self::bool(
                $payload['power_cut']
                ?? $payload['powerCut']
                ?? data_get($attrs, 'powerCut')
            ),
            'panic' => self::bool(
                $payload['panic']
                ?? $payload['sos']
                ?? (in_array(data_get($attrs, 'alarm') ?? $payload['alarm'] ?? null, ['sos', 'panic'], true) ? true : null)
            ),
            'gps_fix' => self::string(
                $payload['gps_fix']
                ?? (($payload['valid'] ?? true) ? 'fix' : 'no_fix')
            ),
        ], fn ($v) => $v !== null);
    }

    private static function string(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function float(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private static function int(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function bool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
