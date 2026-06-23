<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'lat',
        'lng',
        'speed',
        'heading',
        'battery_level',
        'gps_fix',
        'recorded_at',
        'ignition',
        'acc',
        'gsm_signal',
        'gps_signal',
        'satellites',
        'odometer',
        'power_cut',
        'panic',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'ignition' => 'boolean',
        'acc' => 'boolean',
        'power_cut' => 'boolean',
        'panic' => 'boolean',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
