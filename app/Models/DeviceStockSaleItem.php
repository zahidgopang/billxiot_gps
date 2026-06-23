<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceStockSaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'stock_order_id',
        'quantity',
        'unit_price',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(DeviceStockSale::class, 'sale_id');
    }

    public function stockOrder(): BelongsTo
    {
        return $this->belongsTo(DeviceStockOrder::class, 'stock_order_id');
    }
}

