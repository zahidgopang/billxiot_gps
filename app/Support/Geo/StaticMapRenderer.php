<?php

namespace App\Support\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Build static map images for PDF embedding (base64 data URIs).
 *
 * Tries Google Static Maps first, then OpenStreetMap (no key) so PDFs still
 * show a map beside addresses when the browser Maps key is referer-restricted.
 */
final class StaticMapRenderer
{
    private const SPEED_COLORS = [
        'stopped' => '0x1E293BFF',
        'slow' => '0x22C55EFF',
        'medium' => '0xEAB308FF',
        'fast' => '0xEF4444FF',
    ];

    /**
     * Pin map for a single address/location (start or end).
     *
     * @return array{data_uri: ?string, url: ?string, ok: bool, error: ?string}
     */
    public function renderLocationMap(
        float $lat,
        float $lng,
        int $width = 560,
        int $height = 340,
        int $zoom = 15,
        string $label = 'A',
    ): array {
        if (! $this->validCoord($lat, $lng)) {
            return $this->fail('invalid_coords');
        }

        $key = $this->apiKey();
        if ($key !== '') {
            $query = http_build_query([
                'center' => "{$lat},{$lng}",
                'zoom' => (string) $zoom,
                'size' => "{$width}x{$height}",
                'scale' => '2',
                'maptype' => 'roadmap',
                'key' => $key,
            ], '', '&', PHP_QUERY_RFC3986);
            $query .= '&markers='.rawurlencode('color:0xDC2626|size:mid|label:'.$label."|{$lat},{$lng}");
            $google = $this->downloadImage('https://maps.googleapis.com/maps/api/staticmap?'.$query);
            if ($google['ok']) {
                return $google;
            }
        }

        return $this->downloadImage($this->osmLocationUrl($lat, $lng, $width, $height, $zoom));
    }

    /**
     * Route map with optional stop markers. Falls back to OSM, then start pin.
     *
     * @param  list<array<string, mixed>>  $points  lat/lng[/speed] rows
     * @param  list<array{lat: float, lng: float, kind?: string}>  $stopMarkers
     * @return array{data_uri: ?string, url: ?string, ok: bool, error: ?string}
     */
    public function renderRouteMap(
        array $points,
        array $stopMarkers = [],
        int $width = 560,
        int $height = 340,
    ): array {
        $sampled = GooglePolyline::downsample($points, 100);
        if (count($sampled) < 2) {
            $first = $sampled[0] ?? null;
            if ($first !== null) {
                return $this->renderLocationMap(
                    (float) $first['lat'],
                    (float) $first['lng'],
                    $width,
                    $height,
                    14,
                    'S',
                );
            }

            return $this->fail('not_enough_points');
        }

        $start = $sampled[0];
        $end = $sampled[count($sampled) - 1];
        $key = $this->apiKey();

        if ($key !== '') {
            $query = http_build_query([
                'size' => "{$width}x{$height}",
                'scale' => '2',
                'maptype' => 'roadmap',
                'key' => $key,
            ], '', '&', PHP_QUERY_RFC3986);

            foreach ($this->speedPathParams($points, $sampled) as $path) {
                $query .= '&path='.rawurlencode($path);
            }

            $query .= '&markers='.rawurlencode('color:0x16A34A|size:mid|label:S|'.$start['lat'].','.$start['lng']);
            $query .= '&markers='.rawurlencode('color:0xDC2626|size:mid|label:E|'.$end['lat'].','.$end['lng']);
            foreach ($this->formatStopMarkerParams($stopMarkers, 6) as $marker) {
                $query .= '&markers='.rawurlencode($marker);
            }

            if (strlen($query) > 7500) {
                $query = $this->buildCompactQuery($sampled, $start, $end, $key, $width, $height);
            }

            $google = $this->downloadImage('https://maps.googleapis.com/maps/api/staticmap?'.$query);
            if ($google['ok']) {
                return $google;
            }

            // Retry once with a short single-path Google URL.
            $compact = $this->buildCompactQuery($sampled, $start, $end, $key, $width, $height);
            $googleCompact = $this->downloadImage('https://maps.googleapis.com/maps/api/staticmap?'.$compact);
            if ($googleCompact['ok']) {
                return $googleCompact;
            }
        }

        $osm = $this->downloadImage($this->osmRouteUrl($sampled, $start, $end, $width, $height));
        if ($osm['ok']) {
            return $osm;
        }

        // Last resort: map of the start address location.
        return $this->renderLocationMap(
            (float) $start['lat'],
            (float) $start['lng'],
            $width,
            $height,
            14,
            'S',
        );
    }

    /**
     * Map showing start + end address pins (when a full route image is unavailable).
     *
     * @return array{data_uri: ?string, url: ?string, ok: bool, error: ?string}
     */
    public function renderAddressPairMap(
        float $startLat,
        float $startLng,
        ?float $endLat = null,
        ?float $endLng = null,
        int $width = 560,
        int $height = 340,
    ): array {
        if (! $this->validCoord($startLat, $startLng)) {
            return $this->fail('invalid_coords');
        }

        $hasEnd = $endLat !== null && $endLng !== null && $this->validCoord($endLat, $endLng);
        $key = $this->apiKey();

        if ($key !== '') {
            $query = http_build_query([
                'size' => "{$width}x{$height}",
                'scale' => '2',
                'maptype' => 'roadmap',
                'key' => $key,
            ], '', '&', PHP_QUERY_RFC3986);
            $query .= '&markers='.rawurlencode("color:0x16A34A|size:mid|label:S|{$startLat},{$startLng}");
            if ($hasEnd) {
                $query .= '&markers='.rawurlencode("color:0xDC2626|size:mid|label:E|{$endLat},{$endLng}");
                $query .= '&path='.rawurlencode("color:0x2563EBFF|weight:3|{$startLat},{$startLng}|{$endLat},{$endLng}");
            } else {
                $query .= '&center='.rawurlencode("{$startLat},{$startLng}").'&zoom=15';
            }

            $google = $this->downloadImage('https://maps.googleapis.com/maps/api/staticmap?'.$query);
            if ($google['ok']) {
                return $google;
            }
        }

        if ($hasEnd) {
            $url = 'https://staticmap.openstreetmap.de/staticmap.php?'
                .http_build_query([
                    'size' => "{$width}x{$height}",
                    'maptype' => 'mapnik',
                ], '', '&', PHP_QUERY_RFC3986)
                .'&markers='.rawurlencode("{$startLat},{$startLng},lightgreen1")
                .'&markers='.rawurlencode("{$endLat},{$endLng},red-pushpin")
                .'&path='.rawurlencode("{$startLat},{$startLng},{$endLat},{$endLng}");

            $osm = $this->downloadImage($url);
            if ($osm['ok']) {
                return $osm;
            }
        }

        return $this->renderLocationMap($startLat, $startLng, $width, $height, 15, 'S');
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @param  list<array{lat: float, lng: float}>  $fallbackSampled
     * @return list<string>
     */
    private function speedPathParams(array $points, array $fallbackSampled): array
    {
        $withSpeed = [];
        foreach ($points as $point) {
            if (! isset($point['lat'], $point['lng'])) {
                continue;
            }
            $lat = (float) $point['lat'];
            $lng = (float) $point['lng'];
            if (! $this->validCoord($lat, $lng)) {
                continue;
            }
            $withSpeed[] = [
                'lat' => $lat,
                'lng' => $lng,
                'speed' => (float) ($point['speed'] ?? 0),
            ];
        }

        if (count($withSpeed) < 2) {
            return [
                'color:0x2563EBFF|weight:4|enc:'.GooglePolyline::encode($fallbackSampled),
            ];
        }

        $withSpeed = GooglePolyline::downsample($withSpeed, 90);
        if (count($withSpeed) < 2) {
            return [
                'color:0x2563EBFF|weight:4|enc:'.GooglePolyline::encode($fallbackSampled),
            ];
        }

        $paths = [];
        for ($i = 1, $n = count($withSpeed); $i < $n; $i++) {
            $a = $withSpeed[$i - 1];
            $b = $withSpeed[$i];
            $speed = max((float) $a['speed'], (float) $b['speed']);
            $paths[] = [
                'bucket' => $this->speedBucket($speed),
                'a' => ['lat' => $a['lat'], 'lng' => $a['lng']],
                'b' => ['lat' => $b['lat'], 'lng' => $b['lng']],
            ];
        }

        $groups = [];
        $current = null;
        foreach ($paths as $edge) {
            if ($current === null || $current['bucket'] !== $edge['bucket']) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = [
                    'bucket' => $edge['bucket'],
                    'points' => [$edge['a'], $edge['b']],
                ];
            } else {
                $current['points'][] = $edge['b'];
            }
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        usort($groups, fn ($x, $y) => count($y['points']) <=> count($x['points']));
        $groups = array_slice($groups, 0, 6);

        $out = [];
        foreach ($groups as $group) {
            if (count($group['points']) < 2) {
                continue;
            }
            $color = self::SPEED_COLORS[$group['bucket']] ?? self::SPEED_COLORS['slow'];
            $out[] = 'color:'.$color.'|weight:4|enc:'.GooglePolyline::encode($group['points']);
        }

        if ($out === []) {
            $out[] = 'color:0x2563EBFF|weight:4|enc:'.GooglePolyline::encode($fallbackSampled);
        }

        return $out;
    }

    private function speedBucket(float $speedKmh): string
    {
        if ($speedKmh <= 1) {
            return 'stopped';
        }
        if ($speedKmh <= 60) {
            return 'slow';
        }
        if ($speedKmh <= 80) {
            return 'medium';
        }

        return 'fast';
    }

    /**
     * @param  list<array{lat: float, lng: float, kind?: string}>  $stops
     * @return list<string>
     */
    private function formatStopMarkerParams(array $stops, int $max): array
    {
        $out = [];
        $seen = [];
        foreach ($stops as $stop) {
            if (count($out) >= $max) {
                break;
            }
            $lat = isset($stop['lat']) ? (float) $stop['lat'] : null;
            $lng = isset($stop['lng']) ? (float) $stop['lng'] : null;
            if ($lat === null || $lng === null || ! $this->validCoord($lat, $lng)) {
                continue;
            }
            $key = round($lat, 4).':'.round($lng, 4);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $kind = strtolower((string) ($stop['kind'] ?? $stop['motion_key'] ?? 'stop'));
            if (str_contains($kind, 'park') || $kind === 'p') {
                $out[] = 'color:0x2563EB|size:tiny|label:P|'.$lat.','.$lng;
            } elseif (str_contains($kind, 'idle') || $kind === 'i') {
                $out[] = 'color:0xF97316|size:tiny|label:I|'.$lat.','.$lng;
            } else {
                $out[] = 'color:0xEF4444|size:tiny|label:S|'.$lat.','.$lng;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $sampled
     * @param  array{lat: float, lng: float}  $start
     * @param  array{lat: float, lng: float}  $end
     */
    private function buildCompactQuery(
        array $sampled,
        array $start,
        array $end,
        string $key,
        int $width,
        int $height,
    ): string {
        $compact = GooglePolyline::downsample($sampled, 60);
        $query = http_build_query([
            'size' => "{$width}x{$height}",
            'scale' => '2',
            'maptype' => 'roadmap',
            'path' => 'color:0x2563EBFF|weight:4|enc:'.GooglePolyline::encode($compact),
            'key' => $key,
        ], '', '&', PHP_QUERY_RFC3986);
        $query .= '&markers='.rawurlencode('color:0x16A34A|size:mid|label:S|'.$start['lat'].','.$start['lng']);
        $query .= '&markers='.rawurlencode('color:0xDC2626|size:mid|label:E|'.$end['lat'].','.$end['lng']);

        return $query;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $sampled
     * @param  array{lat: float, lng: float}  $start
     * @param  array{lat: float, lng: float}  $end
     */
    private function osmRouteUrl(array $sampled, array $start, array $end, int $width, int $height): string
    {
        $compact = GooglePolyline::downsample($sampled, 40);
        $pathParts = [];
        foreach ($compact as $p) {
            $pathParts[] = $p['lat'].','.$p['lng'];
        }

        $url = 'https://staticmap.openstreetmap.de/staticmap.php?'
            .http_build_query([
                'size' => "{$width}x{$height}",
                'maptype' => 'mapnik',
            ], '', '&', PHP_QUERY_RFC3986);
        $url .= '&markers='.rawurlencode($start['lat'].','.$start['lng'].',lightgreen1');
        $url .= '&markers='.rawurlencode($end['lat'].','.$end['lng'].',red-pushpin');
        if (count($pathParts) >= 2) {
            $url .= '&path='.rawurlencode(implode(',', $pathParts));
        }

        return $url;
    }

    private function osmLocationUrl(float $lat, float $lng, int $width, int $height, int $zoom): string
    {
        return 'https://staticmap.openstreetmap.de/staticmap.php?'
            .http_build_query([
                'center' => "{$lat},{$lng}",
                'zoom' => (string) max(10, min(18, $zoom)),
                'size' => "{$width}x{$height}",
                'maptype' => 'mapnik',
                'markers' => "{$lat},{$lng},red-pushpin",
            ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{data_uri: ?string, url: ?string, ok: bool, error: ?string}
     */
    private function downloadImage(string $url): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => (string) config('app.name', 'BillXiotGPS').'/1.0 (history-pdf-map)',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Static map request failed', ['error' => $e->getMessage()]);

            return $this->fail('request_failed', $url);
        }

        if (! $response->ok()) {
            Log::warning('Static map HTTP error', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 180),
            ]);

            return $this->fail('http_'.$response->status(), $url);
        }

        $body = $response->body();
        $contentType = strtolower((string) $response->header('Content-Type', 'image/png'));
        // Google often returns a small error GIF/PNG with referer-restricted keys.
        if ((! str_contains($contentType, 'image') && ! str_starts_with($body, "\x89PNG") && ! str_starts_with($body, "\xFF\xD8"))
            || strlen($body) < 800) {
            Log::warning('Static map returned non-image / tiny payload', [
                'content_type' => $contentType,
                'bytes' => strlen($body),
                'snippet' => substr($body, 0, 120),
            ]);

            return $this->fail('invalid_image', $url);
        }

        $mime = str_contains($contentType, 'jpeg') || str_starts_with($body, "\xFF\xD8")
            ? 'image/jpeg'
            : 'image/png';

        return [
            'data_uri' => 'data:'.$mime.';base64,'.base64_encode($body),
            'url' => $url,
            'ok' => true,
            'error' => null,
        ];
    }

    /**
     * @return array{data_uri: null, url: ?string, ok: false, error: string}
     */
    private function fail(string $error, ?string $url = null): array
    {
        return [
            'data_uri' => null,
            'url' => $url,
            'ok' => false,
            'error' => $error,
        ];
    }

    private function validCoord(float $lat, float $lng): bool
    {
        return is_finite($lat) && is_finite($lng)
            && abs($lat) <= 90 && abs($lng) <= 180
            && ! ($lat == 0.0 && $lng == 0.0);
    }

    private function apiKey(): string
    {
        $server = trim((string) config('services.google.geocoding_key', ''));
        if ($server !== '') {
            return $server;
        }

        return trim((string) config('services.google.maps_key', ''));
    }
}
