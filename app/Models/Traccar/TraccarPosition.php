<?php

namespace App\Models\Traccar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraccarPosition extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('traccar.tables.positions', 'tc_positions');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TraccarDevice::class, 'deviceid');
    }
}
