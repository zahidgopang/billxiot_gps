<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceStockSale extends Model
{
    public const STATUSES = [
        'issued' => 'Issued',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'invoice_no',
        'client_id',
        'currency',
        'status',
        'issued_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DeviceStockSale $sale) {
            if (blank($sale->invoice_no)) {
                $sale->invoice_no = self::generateInvoiceNo();
            }
            if (blank($sale->issued_at)) {
                $sale->issued_at = now()->toDateString();
            }
        });
    }

    public static function generateInvoiceNo(): string
    {
        $prefix = 'INV-'.now()->format('Y');
        $last = self::query()
            ->where('invoice_no', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('invoice_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeviceStockSaleItem::class, 'sale_id');
    }

    public function totalAmount(): float
    {
        return (float) $this->items()->sum('line_total');
    }
}

