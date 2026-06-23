<?php

namespace App\Support\Traccar;

final class TraccarSpeed
{
    private const KMH_TO_KNOTS = 0.539957;

    private const KNOTS_TO_KMH = 1.852;

    public static function kmhToKnots(float $kmh): float
    {
        return $kmh * self::KMH_TO_KNOTS;
    }

    public static function knotsToKmh(float $knots): float
    {
        return $knots * self::KNOTS_TO_KMH;
    }
}
