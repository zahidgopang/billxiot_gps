<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'scope',
        'scope_id',
        'type',
        'quantity',
        'source_type',
        'source_id',
        'occurred_at',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'scope_id' => 'integer',
        'quantity' => 'integer',
        'occurred_at' => 'datetime',
        'created_by' => 'integer',
        'meta' => 'array',
    ];
}

