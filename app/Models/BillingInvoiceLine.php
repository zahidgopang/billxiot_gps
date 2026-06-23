<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInvoiceLine extends Model
{
    protected $fillable = [
        'billing_invoice_id',
        'line_type',
        'description',
        'quantity',
        'unit_cost',
        'unit_price',
        'line_total',
        'reference_type',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'meta' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
