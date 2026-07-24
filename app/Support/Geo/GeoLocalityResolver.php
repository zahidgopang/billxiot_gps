<?php

namespace App\Support\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve human-friendly city/locality names and full addresses from coordinates.
 */
class GeoLocalityResolver
{
    private const CACHE_DAYS = 90;

    private const FAIL_CACHE_MINUTES = 30;

    /** Skip further Google calls in this process after a hard deny/limit. */
    private static bool $googleDisabled = false;

    /**
     * @param  list<array<string, mixed>>  $addressComponents
     */
    public function fromAddressComponents(array $addressComponents): ?string
    {
        $byType = [];
        foreach ($addressComponents as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $byType[$type] = (string) ($component['long_name'] ?? $component['short_name'] ?? '');
            }
        }

        foreach (['locality', 'postal_town', 'administrative_area_level_2', 'sublocality', 'administrative_area_level_1', 'neighborhood'] as $type) {
            $name = $this->sanitizeLabel($byType[$type] ?? null);
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    public function reverseGeocode(float $lat, float $lng): ?string
    {
        $lat = round($lat, 5);
        $lng = round($lng, 5);

        return $this->rememberGeo("geo:locality:v2:{$lat}:{$lng}", fn () => $this->fetchLocality($lat, $lng));
    }

    /** Full formatted address for report tables (cached). */
    public function reverseGeocodeAddress(float $lat, float $lng): ?string
    {
        $lat = round($lat, 5);
        $lng = round($lng, 5);

        return $this->rememberGeo("geo:address:v2:{$lat}:{$lng}", fn () => $this->fetchFormattedAddress($lat, $lng));
    }

    public function sanitizeLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        if ($label === '') {
            return null;
        }

        if ($this->isPlusCode($label)) {
            return null;
        }

        if (preg_match('/^[A-Z0-9]{4,8}\+[A-Z0-9]{2,3}(?:\s|$)/u', $label)) {
            $label = trim(preg_replace('/^[A-Z0-9]{4,8}\+[A-Z0-9]{2,3}\s*/u', '', $label) ?? '');
        }

        $parts = array_map('trim', explode(',', $label));
        foreach ($parts as $part) {
            if ($part === '' || $this->isPlusCode($part)) {
                continue;
            }
            if (preg_match('/^\d+$/', $part)) {
                continue;
            }
            if (preg_match('/^(Saudi Arabia|Kingdom of Saudi Arabia|المملكة العربية السعودية)$/iu', $part)) {
                continue;
            }

            return $part;
        }

        return $label !== '' && ! $this->isPlusCode($label) ? $label : null;
    }

    /** Keep a readable multi-part street address for report tables. */
    public function cleanFormattedAddress(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        if ($label === '') {
            return null;
        }

        if ($this->isPlusCode($label)) {
            return null;
        }

        if (preg_match('/^[A-Z0-9]{4,8}\+[A-Z0-9]{2,3}(?:\s|$)/u', $label)) {
            $label = trim(preg_replace('/^[A-Z0-9]{4,8}\+[A-Z0-9]{2,3}\s*,?\s*/u', '', $label) ?? '');
        }

        $label = trim($label, " \t\n\r\0\x0B,");

        return $label !== '' ? $label : null;
    }

    public function isPlusCode(string $text): bool
    {
        $text = trim($text);

        return (bool) preg_match('/^[23456789CFGHJMPQRVWX]{4,8}\+[23456789CFGHJMPQRVWX]{2,3}$/iu', $text);
    }

    /**
     * @param  array<string, mixed>|null  $googleResult
     */
    public function fromGoogleResult(?array $googleResult): ?string
    {
        if ($googleResult === null) {
            return null;
        }

        $fromComponents = $this->fromAddressComponents($googleResult['address_components'] ?? []);
        if ($fromComponents !== null) {
            return $fromComponents;
        }

        return $this->sanitizeLabel($googleResult['formatted_address'] ?? null);
    }

    /**
     * @param  callable(): (?string)  $fetcher
     */
    private function rememberGeo(string $cacheKey, callable $fetcher): ?string
    {
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if ($cached === '__miss__' || $cached === null || $cached === '') {
                return null;
            }

            return is_string($cached) ? $cached : null;
        }

        $value = $fetcher();
        if (is_string($value) && $value !== '') {
            Cache::put($cacheKey, $value, now()->addDays(self::CACHE_DAYS));

            return $value;
        }

        // Short-lived negative cache so denied API keys are retried after config changes.
        Cache::put($cacheKey, '__miss__', now()->addMinutes(self::FAIL_CACHE_MINUTES));

        return null;
    }

    private function geocodingApiKey(): string
    {
        $server = trim((string) config('services.google.geocoding_key', ''));
        if ($server !== '') {
            return $server;
        }

        return trim((string) config('services.google.maps_key', ''));
    }

    private function fetchLocality(float $lat, float $lng): ?string
    {
        $fromGoogle = $this->fetchGoogleAddress($lat, $lng, true);
        if ($fromGoogle !== null) {
            return $this->sanitizeLabel($fromGoogle) ?? $fromGoogle;
        }

        $fromOsm = $this->fetchNominatimAddress($lat, $lng);
        if ($fromOsm !== null) {
            return $this->sanitizeLabel($fromOsm) ?? $fromOsm;
        }

        return null;
    }

    private function fetchFormattedAddress(float $lat, float $lng): ?string
    {
        $fromGoogle = $this->fetchGoogleAddress($lat, $lng, false);
        if ($fromGoogle !== null) {
            return $this->cleanFormattedAddress($fromGoogle) ?? $fromGoogle;
        }

        $fromOsm = $this->fetchNominatimAddress($lat, $lng);
        if ($fromOsm !== null) {
            return $this->cleanFormattedAddress($fromOsm) ?? $fromOsm;
        }

        return null;
    }

    private function fetchGoogleAddress(float $lat, float $lng, bool $localityOnly): ?string
    {
        if (self::$googleDisabled) {
            return null;
        }

        $key = $this->geocodingApiKey();
        if ($key === '') {
            return null;
        }

        try {
            $params = [
                'latlng' => "{$lat},{$lng}",
                'key' => $key,
            ];
            if ($localityOnly) {
                $params['result_type'] = 'locality|postal_town|administrative_area_level_2|sublocality';
            }

            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', $params);
        } catch (\Throwable $e) {
            Log::debug('Google reverse geocode failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $json = $response->json();
        $status = (string) ($json['status'] ?? '');
        if ($status !== 'OK' || empty($json['results'][0])) {
            if (in_array($status, ['REQUEST_DENIED', 'OVER_QUERY_LIMIT'], true)) {
                self::$googleDisabled = true;
                Log::warning('Google reverse geocode disabled for this request', [
                    'status' => $status,
                    'error' => $json['error_message'] ?? null,
                ]);
            } elseif ($status === 'UNKNOWN_ERROR') {
                Log::warning('Google reverse geocode denied/limited', [
                    'status' => $status,
                    'error' => $json['error_message'] ?? null,
                ]);
            }

            return null;
        }

        $formatted = (string) ($json['results'][0]['formatted_address'] ?? '');
        if ($formatted !== '') {
            return $formatted;
        }

        return $this->fromGoogleResult($json['results'][0]);
    }

    /** OpenStreetMap fallback when Google browser-restricted keys cannot be used server-side. */
    private static ?float $lastNominatimAt = null;

    private function fetchNominatimAddress(float $lat, float $lng): ?string
    {
        // Nominatim fair-use: ~1 request/second.
        if (self::$lastNominatimAt !== null) {
            $wait = 1.05 - (microtime(true) - self::$lastNominatimAt);
            if ($wait > 0) {
                usleep((int) round($wait * 1_000_000));
            }
        }
        self::$lastNominatimAt = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => (string) config('app.name', 'BillXiotGPS').'/1.0 (reports-geocoder)',
                    'Accept-Language' => app()->getLocale() === 'ar' ? 'ar,en' : 'en,ar',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);
        } catch (\Throwable $e) {
            Log::debug('Nominatim reverse geocode failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $json = $response->json();
        $display = trim((string) ($json['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        $address = $json['address'] ?? [];
        if (! is_array($address)) {
            return null;
        }

        $parts = array_filter([
            $address['road'] ?? $address['pedestrian'] ?? null,
            $address['neighbourhood'] ?? $address['suburb'] ?? $address['quarter'] ?? null,
            $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['county'] ?? null,
            $address['state'] ?? null,
        ], static fn ($v) => is_string($v) && trim($v) !== '');

        return $parts !== [] ? implode(', ', $parts) : null;
    }
}
