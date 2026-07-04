<?php

namespace App\Support\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve human-friendly city/locality names from coordinates or geocoder payloads.
 */
class GeoLocalityResolver
{
    private const CACHE_DAYS = 90;

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

        return Cache::remember(
            "geo:locality:{$lat}:{$lng}",
            now()->addDays(self::CACHE_DAYS),
            fn () => $this->fetchLocality($lat, $lng),
        );
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

    private function fetchLocality(float $lat, float $lng): ?string
    {
        $key = (string) config('services.google.maps_key');
        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'result_type' => 'locality|postal_town|administrative_area_level_2|sublocality',
                'key' => $key,
            ]);
        } catch (\Throwable $e) {
            Log::debug('Locality reverse geocode failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $json = $response->json();
        if (($json['status'] ?? '') !== 'OK' || empty($json['results'])) {
            return null;
        }

        foreach ($json['results'] as $result) {
            $name = $this->fromGoogleResult($result);
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }
}
