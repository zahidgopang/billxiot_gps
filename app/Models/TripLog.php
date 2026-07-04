<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLog extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_REACHED_DESTINATION = 'reached_destination';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'device_id',
        'route_id',
        'current_checkpoint_sequence',
        'current_segment_label',
        'percentage_completed',
        'distance_travelled_km',
        'expected_distance_km',
        'actual_distance_km',
        'extra_distance_km',
        'corridor_progress_km',
        'remaining_distance_km',
        'eta_at',
        'status',
        'started_at',
        'start_lat',
        'start_lng',
        'reached_destination_at',
        'completed_at',
        'last_lat',
        'last_lng',
        'checkpoint_timings',
        'navigation_context',
        'actual_route_polyline',
        'navigation_route_polyline',
        'trip_statistics',
        'trip_summary',
    ];

    protected function casts(): array
    {
        return [
            'current_checkpoint_sequence' => 'integer',
            'percentage_completed' => 'float',
            'distance_travelled_km' => 'float',
            'expected_distance_km' => 'float',
            'actual_distance_km' => 'float',
            'extra_distance_km' => 'float',
            'corridor_progress_km' => 'float',
            'remaining_distance_km' => 'float',
            'eta_at' => 'datetime',
            'started_at' => 'datetime',
            'start_lat' => 'float',
            'start_lng' => 'float',
            'reached_destination_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'checkpoint_timings' => 'array',
            'navigation_context' => 'array',
            'trip_statistics' => 'array',
            'trip_summary' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RoutePlan::class, 'route_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED], true);
    }

    public function isSessionActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_IN_PROGRESS,
            self::STATUS_REACHED_DESTINATION,
        ], true);
    }

    public function canStartTrip(): bool
    {
        return in_array($this->status, [
            self::STATUS_NOT_STARTED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ], true);
    }
}
