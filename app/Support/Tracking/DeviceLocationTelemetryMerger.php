<?php

namespace App\Support\Tracking;

use App\Models\DeviceLocation;

/**
 * Fills missing telemetry on a primary position from a secondary source (e.g. Traccar + legacy row).
 */
final class DeviceLocationTelemetryMerger
{
    /**
     * @param  list<string>  $fields
     */
    public static function merge(DeviceLocation $primary, ?DeviceLocation $supplement, array $fields = []): DeviceLocation
    {
        if ($supplement === null) {
            return $primary;
        }

        $fields = $fields ?: [
            'gsm_signal',
            'gps_signal',
            'satellites',
            'battery_level',
            'odometer',
            'gps_fix',
            'ignition',
            'acc',
        ];

        foreach ($fields as $field) {
            if (($primary->{$field} === null || $primary->{$field} === '')
                && $supplement->{$field} !== null
                && $supplement->{$field} !== '') {
                $primary->{$field} = $supplement->{$field};
            }
        }

        if ($primary->recorded_at && $supplement->recorded_at) {
            if ($supplement->recorded_at->greaterThan($primary->recorded_at)) {
                $primary->recorded_at = $supplement->recorded_at;
            }
        } elseif (! $primary->recorded_at && $supplement->recorded_at) {
            $primary->recorded_at = $supplement->recorded_at;
        }

        return $primary;
    }
}
