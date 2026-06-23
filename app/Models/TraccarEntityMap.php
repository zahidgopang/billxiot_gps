<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TraccarEntityMap extends Model
{
    public const TYPE_DEVICE = 'device';

    public const TYPE_USER = 'user';

    public const TYPE_GEOFENCE = 'geofence';

    protected $table = 'traccar_entity_map';

    protected $fillable = [
        'entity_type',
        'laravel_id',
        'traccar_id',
    ];
}
