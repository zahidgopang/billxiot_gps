<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommandLog extends Model
{
    protected $table = 'device_command_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'device_id' => 'integer',
            'traccar_device_id' => 'integer',
            'requested_by' => 'integer',
            'queue_id' => 'integer',
            'last_event_id' => 'integer',
            'stages' => 'array',
            'timeout_at' => 'datetime',
            'delivered_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
