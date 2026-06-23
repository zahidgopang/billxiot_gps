<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy morph target for activity log rows created before stock was restructured as orders.
 *
 * @deprecated Use {@see DeviceStockOrder}. Subject IDs from old unit rows may not resolve.
 */
class DeviceStockUnit extends Model
{
    protected $table = 'device_stock_orders';

    public $timestamps = true;

    protected $guarded = [];

    public function getNameAttribute(): ?string
    {
        return $this->attributes['order_code'] ?? null;
    }
}
