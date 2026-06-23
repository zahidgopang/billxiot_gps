<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeofenceEvent extends Model
{
    protected $fillable = [
        'device_id', 'geofence_id', 'event', 'time'
    ];

    protected $casts = [
        'time' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function geofence()
    {
        return $this->belongsTo(Geofence::class);
    }
}

