<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryFifoLayer extends Model
{
    protected $fillable = [
        'product_id',
        'scope',
        'scope_id',
        'received_at',
        'unit_cost',
        'currency',
        'remaining_qty',
        'source_purchase_order_id',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'scope_id' => 'integer',
        'received_at' => 'datetime',
        'unit_cost' => 'decimal:2',
        'remaining_qty' => 'integer',
        'source_purchase_order_id' => 'integer',
    ];
}

