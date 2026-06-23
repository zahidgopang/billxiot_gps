<?php

namespace App\Models\Traccar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TraccarDevice extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('traccar.tables.devices', 'tc_devices');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(TraccarPosition::class, 'deviceid');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TraccarEvent::class, 'deviceid');
    }

    public function geofences(): BelongsToMany
    {
        return $this->belongsToMany(
            TraccarGeofence::class,
            config('traccar.tables.device_geofence', 'tc_device_geofence'),
            'deviceid',
            'geofenceid'
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            TraccarUser::class,
            config('traccar.tables.user_device', 'tc_user_device'),
            'deviceid',
            'userid'
        );
    }
}
