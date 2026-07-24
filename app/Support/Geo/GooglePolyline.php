<?php

namespace App\Support\Geo;

/**
 * Google Encoded Polyline Algorithm Format.
 *
 * @see https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class GooglePolyline
{
    /**
     * @param  list<array{0: float, 1: float}|array{lat: float, lng: float}>  $points
     */
    public static function encode(array $points): string
    {
        $lastLat = 0;
        $lastLng = 0;
        $result = '';

        foreach ($points as $point) {
            if (isset($point['lat'], $point['lng'])) {
                $lat = (float) $point['lat'];
                $lng = (float) $point['lng'];
            } else {
                $lat = (float) ($point[0] ?? 0);
                $lng = (float) ($point[1] ?? 0);
            }

            $ilat = (int) round($lat * 1e5);
            $ilng = (int) round($lng * 1e5);

            $result .= self::encodeSigned($ilat - $lastLat);
            $result .= self::encodeSigned($ilng - $lastLng);

            $lastLat = $ilat;
            $lastLng = $ilng;
        }

        return $result;
    }

    /**
     * Keep first/last points and evenly sample the middle for Static Maps URL limits.
     *
     * @param  list<array<string, mixed>>  $points  rows with lat/lng keys
     * @return list<array{lat: float, lng: float}>
     */
    public static function downsample(array $points, int $maxPoints = 120): array
    {
        $normalized = [];
        foreach ($points as $point) {
            $lat = isset($point['lat']) ? (float) $point['lat'] : null;
            $lng = isset($point['lng']) ? (float) $point['lng'] : null;
            if ($lat === null || $lng === null || ! is_finite($lat) || ! is_finite($lng)) {
                continue;
            }
            if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
                continue;
            }
            $row = ['lat' => $lat, 'lng' => $lng];
            if (isset($point['speed']) && is_numeric($point['speed'])) {
                $row['speed'] = (float) $point['speed'];
            }
            $normalized[] = $row;
        }

        $count = count($normalized);
        if ($count <= $maxPoints || $maxPoints < 3) {
            return $normalized;
        }

        $out = [$normalized[0]];
        $step = ($count - 1) / ($maxPoints - 1);
        for ($i = 1; $i < $maxPoints - 1; $i++) {
            $idx = (int) round($i * $step);
            $out[] = $normalized[$idx];
        }
        $out[] = $normalized[$count - 1];

        return $out;
    }

    private static function encodeSigned(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $chunk = '';

        while ($value >= 0x20) {
            $chunk .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        $chunk .= chr($value + 63);

        return $chunk;
    }
}
