<?php

namespace App\Support\Geo;

final class GeoMath
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Closest point on segment AB to point P; returns [lat, lng, t] where t is 0..1 along segment.
     *
     * @return array{lat: float, lng: float, t: float, distance_km: float}
     */
    public static function projectOntoSegment(
        float $pLat,
        float $pLng,
        float $aLat,
        float $aLng,
        float $bLat,
        float $bLng
    ): array {
        $ax = deg2rad($aLng);
        $ay = deg2rad($aLat);
        $bx = deg2rad($bLng);
        $by = deg2rad($bLat);
        $px = deg2rad($pLng);
        $py = deg2rad($pLat);

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len2 = $dx * $dx + $dy * $dy;
        $t = $len2 > 0 ? max(0, min(1, (($px - $ax) * $dx + ($py - $ay) * $dy) / $len2)) : 0;

        $projLng = rad2deg($ax + $t * $dx);
        $projLat = rad2deg($ay + $t * $dy);
        $distanceKm = self::haversineKm($pLat, $pLng, $projLat, $projLng);

        return [
            'lat' => $projLat,
            'lng' => $projLng,
            't' => $t,
            'distance_km' => $distanceKm,
        ];
    }
}
