<?php

namespace App\Models\Traccar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TraccarGeofence extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('traccar.tables.geofences', 'tc_geofences');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(
            TraccarDevice::class,
            config('traccar.tables.device_geofence', 'tc_device_geofence'),
            'geofenceid',
            'deviceid'
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            TraccarUser::class,
            config('traccar.tables.user_geofence', 'tc_user_geofence'),
            'geofenceid',
            'userid'
        );
    }
}
