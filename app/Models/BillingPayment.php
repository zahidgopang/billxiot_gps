<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingPayment extends Model
{
    protected $fillable = [
        'billing_invoice_id',
        'amount',
        'payment_method',
        'reference',
        'paid_at',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
