<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    use RespondsWithMobileJson;

    public function address(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $lat = $request->query('lat', $request->input('lat'));
        $lng = $request->query('lng', $request->input('lng'));

        return $this->reverseGeocodeGoogle((float) $lat, (float) $lng);
    }

    private function reverseGeocodeGoogle(float $lat, float $lng)
    {
        $apiKey = config('services.google.maps_key');

        if (! $apiKey) {
            return $this->mobileError('Google Maps API key is not configured', 503);
        }

        $response = Http::timeout(5)->get(
            'https://maps.googleapis.com/maps/api/geocode/json',
            [
                'latlng' => "{$lat},{$lng}",
                'key' => $apiKey,
            ]
        );

        if ($response->successful()) {
            $data = $response->json();

            if (($data['status'] ?? '') === 'OK' && ! empty($data['results'])) {
                return $this->mobileSuccess([
                    'address' => $data['results'][0]['formatted_address'],
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
            }
        }

        return $this->mobileError('Address not found', 404);
    }
}
