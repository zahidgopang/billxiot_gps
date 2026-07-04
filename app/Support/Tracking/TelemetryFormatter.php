<?php

namespace App\Support\Tracking;

final class TelemetryFormatter
{
    public static function odometerKm(mixed $meters): ?float
    {
        if ($meters === null || $meters === '') {
            return null;
        }

        return round(((float) $meters) / 1000, 1);
    }
}
