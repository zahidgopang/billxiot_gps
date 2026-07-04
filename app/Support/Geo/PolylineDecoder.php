<?php

namespace App\Support\Geo;

/**
 * Decode Google encoded polylines.
 *
 * @see https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class PolylineDecoder
{
    /**
     * @return list<array{lat: float, lng: float}>
     */
    public static function decode(string $encoded): array
    {
        $points = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $result = 1;
            $shift = 0;
            do {
                $b = ord($encoded[$index++]) - 63 - 1;
                $result += $b << $shift;
                $shift += 5;
            } while ($b >= 0x1f);
            $lat += ($result & 1) !== 0 ? ~($result >> 1) : ($result >> 1);

            $result = 1;
            $shift = 0;
            do {
                $b = ord($encoded[$index++]) - 63 - 1;
                $result += $b << $shift;
                $shift += 5;
            } while ($b >= 0x1f);
            $lng += ($result & 1) !== 0 ? ~($result >> 1) : ($result >> 1);

            $points[] = [
                'lat' => $lat / 1e5,
                'lng' => $lng / 1e5,
            ];
        }

        return $points;
    }
}
