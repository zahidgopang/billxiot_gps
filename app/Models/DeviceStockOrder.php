<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceStockOrder extends Model
{
    public const STATUSES = [
        'in_stock' => 'In stock',
        'reserved' => 'Reserved',
        'sold' => 'Sold',
        'partial' => 'Partially fulfilled',
        'closed' => 'Closed',
        'repair' => 'Repair queue',
    ];

    public const CONDITIONS = [
        'new' => 'New',
        'refurbished' => 'Refurbished',
        'faulty' => 'Faulty / for repair',
    ];

    protected $fillable = [
        'order_code',
        'quantity',
        'sold_quantity',
        'device_type',
        'brand',
        'model',
        'condition',
        'status',
        'unit_cost',
        'selling_price',
        'currency',
        'warehouse_location',
        'supplier',
        'purchase_order_ref',
        'purchased_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sold_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'purchased_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DeviceStockOrder $order) {
            if (blank($order->order_code)) {
                $order->order_code = self::generateOrderCode();
            }
        });
    }

    public static function generateOrderCode(): string
    {
        $prefix = 'ORD-'.now()->format('Y');
        $last = self::query()
            ->where('order_code', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('order_code');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayLabel(): string
    {
        return trim(implode(' ', array_filter([$this->brand, $this->model])))
            ?: $this->order_code;
    }

    public function unitMarginAmount(): float
    {
        return (float) $this->selling_price - (float) $this->unit_cost;
    }

    public function totalCost(): float
    {
        return (float) $this->unit_cost * max(1, (int) $this->quantity);
    }

    public function totalSellingPrice(): float
    {
        return (float) $this->selling_price * max(1, (int) $this->quantity);
    }

    public function totalMarginAmount(): float
    {
        return $this->totalSellingPrice() - $this->totalCost();
    }

    public function availableQuantity(): int
    {
        $q = (int) $this->quantity;
        $sold = (int) ($this->sold_quantity ?? 0);

        return max(0, $q - $sold);
    }

    public function availableTotalCost(): float
    {
        return (float) $this->unit_cost * $this->availableQuantity();
    }

    public function availableTotalSellingPrice(): float
    {
        return (float) $this->selling_price * $this->availableQuantity();
    }

    public function unitMarginPercent(): ?float
    {
        $cost = (float) $this->unit_cost;
        if ($cost <= 0) {
            return null;
        }

        return round(($this->unitMarginAmount() / $cost) * 100, 1);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', 'in_stock');
    }

    public function scopeHasAvailableStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '>', 'sold_quantity');
    }

    public static function inventoryCostSql(): string
    {
        return '(unit_cost * GREATEST(0, (quantity - sold_quantity)))';
    }

    public static function inventoryRetailSql(): string
    {
        return '(selling_price * GREATEST(0, (quantity - sold_quantity)))';
    }
}
