<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceInventorySummary extends Model
{
    protected $table = 'device_inventory_summary';

    protected $fillable = [
        'product_id',
        'scope',
        'scope_id',
        'total_purchased',
        'total_sold',
        'total_installed',
        'total_returned',
        'available_qty',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'scope_id' => 'integer',
        'total_purchased' => 'integer',
        'total_sold' => 'integer',
        'total_installed' => 'integer',
        'total_returned' => 'integer',
        'available_qty' => 'integer',
    ];
}

