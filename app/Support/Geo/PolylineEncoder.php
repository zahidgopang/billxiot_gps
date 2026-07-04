<?php

namespace App\Support\Geo;

/**
 * Encode coordinates to Google polyline format.
 *
 * @see https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class PolylineEncoder
{
    /**
     * @param  list<array{lat: float, lng: float}>  $points
     */
    public static function encode(array $points): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $point) {
            $lat = (int) round($point['lat'] * 1e5);
            $lng = (int) round($point['lng'] * 1e5);
            $encoded .= self::encodeValue($lat - $prevLat);
            $encoded .= self::encodeValue($lng - $prevLng);
            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $encoded;
    }

    private static function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $encoded = '';

        while ($value >= 0x20) {
            $encoded .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        $encoded .= chr($value + 63);

        return $encoded;
    }
}
