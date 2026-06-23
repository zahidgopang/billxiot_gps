<?php

namespace App\Models\Traccar;

use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Model;

/**
 * tc_device_geofence — links tc_geofences to tc_devices.
 */
class TcDeviceGeofence extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return TraccarSchema::deviceGeofencePivotKeys()['table'];
    }

    public function getKeyName(): string
    {
        return TraccarSchema::deviceGeofencePivotKeys()['geofence'];
    }
}
