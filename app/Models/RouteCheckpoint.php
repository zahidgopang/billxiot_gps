<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteCheckpoint extends Model
{
    protected $fillable = [
        'route_id',
        'sequence',
        'from_location',
        'to_location',
        'from_lat',
        'from_lng',
        'to_lat',
        'to_lng',
        'distance_km',
        'expected_duration_minutes',
        'speed_limit_kmh',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'from_lat' => 'float',
            'from_lng' => 'float',
            'to_lat' => 'float',
            'to_lng' => 'float',
            'distance_km' => 'float',
            'expected_duration_minutes' => 'integer',
            'speed_limit_kmh' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RoutePlan::class, 'route_id');
    }

    public function segmentLabel(): string
    {
        return $this->from_location.' → '.$this->to_location;
    }
}
