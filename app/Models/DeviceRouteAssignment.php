<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceRouteAssignment extends Model
{
    protected $fillable = [
        'device_id',
        'route_id',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RoutePlan::class, 'route_id');
    }
}
