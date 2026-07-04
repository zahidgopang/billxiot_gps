<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutePlan extends Model
{
    protected $table = 'routes';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'start_city',
        'destination_city',
        'start_lat',
        'start_lng',
        'destination_lat',
        'destination_lng',
        'expected_distance_km',
        'expected_duration_minutes',
        'arrival_radius_meters',
        'show_polyline',
        'encoded_polyline',
        'guided_distance_km',
        'guided_duration_minutes',
        'polyline_generated_at',
        'show_progress_bar',
        'auto_complete_on_arrival',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_lat' => 'float',
            'start_lng' => 'float',
            'destination_lat' => 'float',
            'destination_lng' => 'float',
            'expected_distance_km' => 'float',
            'expected_duration_minutes' => 'integer',
            'arrival_radius_meters' => 'integer',
            'show_polyline' => 'boolean',
            'guided_distance_km' => 'float',
            'guided_duration_minutes' => 'integer',
            'polyline_generated_at' => 'datetime',
            'show_progress_bar' => 'boolean',
            'auto_complete_on_arrival' => 'boolean',
        ];
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(RouteCheckpoint::class, 'route_id')->orderBy('sequence');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeviceRouteAssignment::class, 'route_id');
    }

    public function tripLogs(): HasMany
    {
        return $this->hasMany(TripLog::class, 'route_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasStoredPolyline(): bool
    {
        return $this->show_polyline
            && is_string($this->encoded_polyline)
            && $this->encoded_polyline !== '';
    }

    /**
     * @return list<array{lat: float, lng: float}>
     */
    public function storedPathVertices(): array
    {
        if (! $this->hasStoredPolyline()) {
            return [];
        }

        return \App\Support\Geo\PolylineDecoder::decode($this->encoded_polyline);
    }

    /**
     * Ordered polyline vertices for map display.
     *
     * @return list<array{lat: float, lng: float, label: string}>
     */
    public function polylineVertices(): array
    {
        $points = [[
            'lat' => (float) $this->start_lat,
            'lng' => (float) $this->start_lng,
            'label' => $this->start_city,
        ]];

        foreach ($this->checkpoints as $checkpoint) {
            $points[] = [
                'lat' => (float) $checkpoint->to_lat,
                'lng' => (float) $checkpoint->to_lng,
                'label' => $checkpoint->to_location,
            ];
        }

        $last = end($points);
        $destLat = (float) $this->destination_lat;
        $destLng = (float) $this->destination_lng;
        if (! $last || abs($last['lat'] - $destLat) > 0.0001 || abs($last['lng'] - $destLng) > 0.0001) {
            $points[] = [
                'lat' => $destLat,
                'lng' => $destLng,
                'label' => $this->destination_city,
            ];
        }

        return $points;
    }

    public function routeLabel(): string
    {
        return trim($this->start_city) . ' → ' . trim($this->destination_city);
    }

    public function recalculateTotalsFromCheckpoints(): void
    {
        $distance = (float) $this->checkpoints->sum('distance_km');
        $duration = (int) $this->checkpoints->sum('expected_duration_minutes');

        if ($distance > 0) {
            $this->expected_distance_km = $distance;
        }
        if ($duration > 0) {
            $this->expected_duration_minutes = $duration;
        }
    }
}
