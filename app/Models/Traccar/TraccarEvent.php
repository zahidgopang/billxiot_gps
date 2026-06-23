<?php

namespace App\Models\Traccar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraccarEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('traccar.tables.events', 'tc_events');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TraccarDevice::class, 'deviceid');
    }
}
