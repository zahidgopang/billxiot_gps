<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleEventRead extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'vehicle_event_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicleEvent(): BelongsTo
    {
        return $this->belongsTo(VehicleEvent::class);
    }
}
