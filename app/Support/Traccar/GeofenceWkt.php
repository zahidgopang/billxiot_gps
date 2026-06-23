<?php

namespace App\Support\Traccar;

/**
 * WKT for Traccar tc_geofences.area.
 *
 * Traccar parses POLYGON/CIRCLE as latitude first, longitude second (not standard WKT lon/lat).
 *
 * @see https://github.com/traccar/traccar/blob/master/src/main/java/org/traccar/geofence/GeofencePolygon.java
 * @see https://github.com/traccar/traccar/blob/master/src/main/java/org/traccar/geofence/GeofenceCircle.php
 */
final class GeofenceWkt
{
    /**
     * @param  array<int, array{0: float|int, 1: float|int}>|string|null  $coords  [lat, lng] pairs (Google Maps)
     * @param  array{0: float|int, 1: float|int}|string|null  $center  [lat, lng]
     */
    public static function fromLaravel(string $type, array|string|null $coords, array|string|null $center, ?int $radiusMeters): string
    {
        if ($type === 'circle') {
            $centerArr = self::normalizePointList($center);

            if ($centerArr === null || count($centerArr) < 2) {
                return 'CIRCLE (0 0, 100)';
            }

            $lat = (float) $centerArr[0];
            $lng = (float) $centerArr[1];
            $radius = (float) ($radiusMeters ?? 100);

            return sprintf('CIRCLE (%f %f, %f)', $lat, $lng, $radius);
        }

        $coordsArr = self::normalizePolygonCoords($coords);

        if ($coordsArr === null || count($coordsArr) < 3) {
            return 'POLYGON ((0 0, 0 0, 0 0, 0 0))';
        }

        $points = [];
        foreach ($coordsArr as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }
            $lat = (float) $point[0];
            $lng = (float) $point[1];
            $points[] = sprintf('%f %f', $lat, $lng);
        }

        if (count($points) < 3) {
            return 'POLYGON ((0 0, 0 0, 0 0, 0 0))';
        }

        if ($points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        return 'POLYGON ((' . implode(', ', $points) . '))';
    }

    /**
     * @return array<int, array{0: float|int, 1: float|int}>|null
     */
    private static function normalizePolygonCoords(array|string|null $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @return array{0: float|int, 1: float|int}|null
     */
    private static function normalizePointList(array|string|null $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : null;
    }

    /** Whether a GPS point is inside a Traccar geofence area string or Laravel shape meta. */
    public static function containsPoint(
        float $lat,
        float $lng,
        ?string $area,
        string $type = 'polygon',
        array|string|null $coords = null,
        array|string|null $center = null,
        ?int $radiusMeters = null
    ): bool {
        $coordsArr = self::normalizePolygonCoords($coords);
        if ($type === 'polygon' && is_array($coordsArr) && count($coordsArr) >= 3) {
            return self::pointInPolygon($lat, $lng, $coordsArr);
        }

        $centerArr = self::normalizePointList($center);
        if ($type === 'circle' && is_array($centerArr) && count($centerArr) >= 2) {
            $distanceM = self::haversineMeters($lat, $lng, (float) $centerArr[0], (float) $centerArr[1]);

            return $distanceM <= (float) ($radiusMeters ?? 100);
        }

        if (! is_string($area) || trim($area) === '') {
            return false;
        }

        $area = trim($area);

        if (str_starts_with(strtoupper($area), 'CIRCLE')) {
            if (preg_match('/CIRCLE\s*\(\s*([-\d.]+)\s+([-\d.]+)\s*,\s*([-\d.]+)\s*\)/i', $area, $m)) {
                $distanceM = self::haversineMeters($lat, $lng, (float) $m[1], (float) $m[2]);

                return $distanceM <= (float) $m[3];
            }

            return false;
        }

        if (preg_match('/POLYGON\s*\(\(([^)]+)\)\)/i', $area, $m)) {
            $pairs = preg_split('/\s*,\s*/', trim($m[1]));
            $polygon = [];
            foreach ($pairs as $pair) {
                $parts = preg_split('/\s+/', trim($pair));
                if (count($parts) >= 2) {
                    $polygon[] = [(float) $parts[0], (float) $parts[1]];
                }
            }

            if (count($polygon) >= 3) {
                return self::pointInPolygon($lat, $lng, $polygon);
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{0: float|int, 1: float|int}>  $polygon  [lat, lng] pairs
     */
    public static function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $x = $lng;
        $y = $lat;
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $polygon[$i][1];
            $yi = (float) $polygon[$i][0];
            $xj = (float) $polygon[$j][1];
            $yj = (float) $polygon[$j][0];
            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-9) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * asin(sqrt($a));
    }

    /**
     * Parse Traccar area WKT into Laravel/mobile shape fields (lat/lng order).
     *
     * @return array{type: string, coords: ?array, center: ?array, radius: ?int}|null
     */
    public static function parseArea(?string $area): ?array
    {
        if (! is_string($area) || trim($area) === '') {
            return null;
        }

        $area = trim($area);

        if (preg_match('/CIRCLE\s*\(\s*([-\d.]+)\s+([-\d.]+)\s*,\s*([-\d.]+)\s*\)/i', $area, $m)) {
            return [
                'type' => 'circle',
                'coords' => null,
                'center' => [(float) $m[1], (float) $m[2]],
                'radius' => (int) round((float) $m[3]),
            ];
        }

        if (preg_match('/POLYGON\s*\(\(([^)]+)\)\)/i', $area, $m)) {
            $pairs = preg_split('/\s*,\s*/', trim($m[1]));
            $coords = [];
            foreach ($pairs as $pair) {
                $parts = preg_split('/\s+/', trim($pair));
                if (count($parts) >= 2) {
                    $coords[] = [(float) $parts[0], (float) $parts[1]];
                }
            }

            if (count($coords) >= 3) {
                $first = $coords[0];
                $last = $coords[count($coords) - 1];
                if ($first[0] === $last[0] && $first[1] === $last[1]) {
                    array_pop($coords);
                }

                return [
                    'type' => 'polygon',
                    'coords' => $coords,
                    'center' => null,
                    'radius' => null,
                ];
            }
        }

        return null;
    }

    /**
     * Ensure API/mobile clients always receive drawable shape data.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>|string|null  $coords
     * @param  array{0: float|int, 1: float|int}|string|null  $center
     * @return array{type: string, coords: ?array, center: ?array, radius: ?int}
     */
    public static function resolveShape(
        string $type,
        array|string|null $coords = null,
        array|string|null $center = null,
        ?int $radius = null,
        ?string $area = null,
    ): array {
        $coordsArr = self::normalizePolygonCoords($coords);
        $centerArr = self::normalizePointList($center);
        $normalizedType = strtolower($type ?: 'polygon');

        if ($normalizedType === 'circle' && is_array($centerArr) && $radius !== null) {
            return [
                'type' => 'circle',
                'coords' => null,
                'center' => $centerArr,
                'radius' => (int) $radius,
            ];
        }

        if ($normalizedType === 'polygon' && is_array($coordsArr) && count($coordsArr) >= 3) {
            return [
                'type' => 'polygon',
                'coords' => $coordsArr,
                'center' => null,
                'radius' => null,
            ];
        }

        $parsed = self::parseArea($area);
        if ($parsed !== null) {
            return $parsed;
        }

        return [
            'type' => $normalizedType,
            'coords' => $coordsArr,
            'center' => $centerArr,
            'radius' => $radius,
        ];
    }
}
