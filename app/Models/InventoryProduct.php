<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryProduct extends Model
{
    protected $fillable = [
        'device_type',
        'brand',
        'model',
        'sku',
    ];

    protected $casts = [
        'device_type' => 'string',
        'brand' => 'string',
        'model' => 'string',
        'sku' => 'string',
    ];
}

