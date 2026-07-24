<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\DeviceLocation;
use App\Support\Traccar\TraccarAppFields;

class DeviceFuelService
{
    public const UNIT_L_PER_100KM = 'l_per_100km';

    public const UNIT_KM_PER_L = 'km_per_l';

    public const SENSOR_LITERS = 'liters';

    public const SENSOR_PERCENT = 'percent';

    /** Ignore tiny sensor noise when detecting consumption. */
    private const MIN_DROP_L = 0.2;

    /** Treat sudden increases above this as refills (liters). */
    public const MIN_REFILL_L = 3.0;

    public function consumptionLPer100km(Device $device): ?float
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_FUEL_CONSUMPTION_L_PER_100KM
        );

        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (float) $raw;

        return $value > 0 ? round($value, 2) : null;
    }

    public function efficiencyUnit(Device $device): string
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_FUEL_EFFICIENCY_UNIT
        );

        return $raw === self::UNIT_KM_PER_L ? self::UNIT_KM_PER_L : self::UNIT_L_PER_100KM;
    }

    public function tankCapacityL(Device $device): ?float
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_FUEL_TANK_CAPACITY_L
        );

        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (float) $raw;

        return $value > 0 ? round($value, 1) : null;
    }

    public function sensorUnit(Device $device): string
    {
        $raw = TraccarAppFields::get(
            $device->getTraccarAttributesJson(),
            TraccarAppFields::KEY_FUEL_SENSOR_UNIT
        );

        return $raw === self::SENSOR_PERCENT ? self::SENSOR_PERCENT : self::SENSOR_LITERS;
    }

    /**
     * @param  array{
     *   fuel_consumption_l_per_100km?: mixed,
     *   fuel_efficiency_unit?: mixed,
     *   fuel_tank_capacity_l?: mixed,
     *   fuel_sensor_unit?: mixed
     * }  $data
     */
    public function syncSettings(Device $device, array $data): void
    {
        $patch = [];

        if (array_key_exists('fuel_consumption_l_per_100km', $data)) {
            $raw = $data['fuel_consumption_l_per_100km'];
            $patch[TraccarAppFields::KEY_FUEL_CONSUMPTION_L_PER_100KM] = ($raw === null || $raw === '')
                ? null
                : max(0.1, round((float) $raw, 2));
        }

        if (array_key_exists('fuel_efficiency_unit', $data)) {
            $unit = (string) ($data['fuel_efficiency_unit'] ?? '');
            $patch[TraccarAppFields::KEY_FUEL_EFFICIENCY_UNIT] = $unit === self::UNIT_KM_PER_L
                ? self::UNIT_KM_PER_L
                : self::UNIT_L_PER_100KM;
        }

        if (array_key_exists('fuel_tank_capacity_l', $data)) {
            $raw = $data['fuel_tank_capacity_l'];
            $patch[TraccarAppFields::KEY_FUEL_TANK_CAPACITY_L] = ($raw === null || $raw === '')
                ? null
                : max(1, round((float) $raw, 1));
        }

        if (array_key_exists('fuel_sensor_unit', $data)) {
            $unit = (string) ($data['fuel_sensor_unit'] ?? '');
            $patch[TraccarAppFields::KEY_FUEL_SENSOR_UNIT] = $unit === self::SENSOR_PERCENT
                ? self::SENSOR_PERCENT
                : self::SENSOR_LITERS;
        }

        if ($patch !== []) {
            $device->patchTraccarAppAttributes($patch);
        }
    }

    /**
     * @return array{
     *   consumption_l_per_100km: ?float,
     *   efficiency_unit: string,
     *   tank_capacity_l: ?float,
     *   sensor_unit: string
     * }
     */
    public function settings(Device $device): array
    {
        return [
            'consumption_l_per_100km' => $this->consumptionLPer100km($device),
            'efficiency_unit' => $this->efficiencyUnit($device),
            'tank_capacity_l' => $this->tankCapacityL($device),
            'sensor_unit' => $this->sensorUnit($device),
        ];
    }

    public function estimatedLiters(float $distanceKm, ?float $rateLPer100km): ?float
    {
        if ($rateLPer100km === null || $rateLPer100km <= 0 || $distanceKm <= 0) {
            return null;
        }

        return round($distanceKm * $rateLPer100km / 100, 2);
    }

    public function efficiencyValue(float $distanceKm, ?float $liters, string $unit): ?float
    {
        if ($liters === null || $liters <= 0 || $distanceKm <= 0) {
            return null;
        }

        if ($unit === self::UNIT_KM_PER_L) {
            return round($distanceKm / $liters, 2);
        }

        return round(($liters / $distanceKm) * 100, 2);
    }

    /**
     * Convert a raw sensor reading into liters.
     */
    public function readingToLiters(mixed $raw, Device $device): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;
        if (! is_finite($value) || $value < 0) {
            return null;
        }

        $tank = $this->tankCapacityL($device);
        if ($this->sensorUnit($device) === self::SENSOR_PERCENT) {
            if ($tank === null || $tank <= 0) {
                return null;
            }

            return round(min(100, $value) / 100 * $tank, 2);
        }

        return round($value, 2);
    }

    /**
     * Sum fuel drops between consecutive sensor readings; treat large rises as refills.
     *
     * @param  list<DeviceLocation>  $sortedPoints
     * @return array{liters: float, sample_count: int, refill_count: int, has_sensor: bool}
     */
    public function sensorConsumedLiters(array $sortedPoints, Device $device): array
    {
        $prev = null;
        $consumed = 0.0;
        $samples = 0;
        $refills = 0;

        foreach ($sortedPoints as $point) {
            $liters = $this->readingToLiters($point->fuel ?? null, $device);
            if ($liters === null) {
                continue;
            }
            $samples++;
            if ($prev === null) {
                $prev = $liters;
                continue;
            }

            $delta = $prev - $liters;
            if ($delta >= self::MIN_DROP_L) {
                $consumed += $delta;
            } elseif (($liters - $prev) >= self::MIN_REFILL_L) {
                $refills++;
            }
            $prev = $liters;
        }

        return [
            'liters' => round($consumed, 2),
            'sample_count' => $samples,
            'refill_count' => $refills,
            'has_sensor' => $samples >= 2,
        ];
    }

    /**
     * Prefer sensor consumption when enough readings exist; otherwise estimate from rate × distance.
     *
     * @param  list<DeviceLocation>  $sortedPoints
     * @return array{
     *   fuel_liters: ?float,
     *   method: string,
     *   efficiency: ?float,
     *   efficiency_unit: string,
     *   estimated_liters: ?float,
     *   sensor_liters: ?float,
     *   sensor_samples: int,
     *   refill_count: int,
     *   rate_l_per_100km: ?float
     * }
     */
    public function resolveConsumption(Device $device, float $distanceKm, array $sortedPoints = []): array
    {
        $rate = $this->consumptionLPer100km($device);
        $unit = $this->efficiencyUnit($device);
        $estimated = $this->estimatedLiters($distanceKm, $rate);
        $sensor = $this->sensorConsumedLiters($sortedPoints, $device);

        $method = 'unconfigured';
        $fuel = null;
        $sensorLiters = $sensor['has_sensor'] ? $sensor['liters'] : null;

        if ($sensor['has_sensor'] && $sensor['liters'] > 0) {
            $fuel = $sensor['liters'];
            $method = 'sensor';
        } elseif ($estimated !== null) {
            $fuel = $estimated;
            $method = 'estimated';
        } elseif ($sensor['has_sensor']) {
            $fuel = $sensor['liters'];
            $method = 'sensor';
        }

        return [
            'fuel_liters' => $fuel,
            'method' => $method,
            'efficiency' => $this->efficiencyValue($distanceKm, $fuel, $unit),
            'efficiency_unit' => $unit,
            'estimated_liters' => $estimated,
            'sensor_liters' => $sensorLiters,
            'sensor_samples' => $sensor['sample_count'],
            'refill_count' => $sensor['refill_count'],
            'rate_l_per_100km' => $rate,
        ];
    }
}
