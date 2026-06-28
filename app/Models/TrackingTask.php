<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingTask extends Model
{
    protected $table = 'tracking_tasks';

    protected $fillable = [
        'device_id',
        'created_by',
        'name',
        'start',
        'destination',
        'priority',
        'status',
        'time_from',
        'time_to',
    ];

    protected $casts = [
        'device_id' => 'integer',
        'created_by' => 'integer',
        'time_from' => 'datetime',
        'time_to' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
