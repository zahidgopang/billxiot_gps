<?php

namespace App\Models;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    protected $fillable = [
        'invoice_no',
        'invoice_type',
        'client_id',
        'user_id',
        'subscription_id',
        'subtotal',
        'tax_amount',
        'total',
        'amount_paid',
        'balance_due',
        'currency',
        'status',
        'due_date',
        'issued_at',
        'paid_at',
        'cancelled_at',
        'cancelled_by',
        'notes',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function typeEnum(): BillingInvoiceType
    {
        return BillingInvoiceType::from($this->invoice_type);
    }

    public function statusEnum(): BillingInvoiceStatus
    {
        return BillingInvoiceStatus::from($this->status);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function lines()
    {
        return $this->hasMany(BillingInvoiceLine::class);
    }

    public function payments()
    {
        return $this->hasMany(BillingPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === BillingInvoiceStatus::Cancelled->value;
    }

    public function isPlatformType(): bool
    {
        return $this->invoice_type === BillingInvoiceType::Platform->value;
    }

    public function isClientType(): bool
    {
        return $this->invoice_type === BillingInvoiceType::Client->value;
    }

    /** Paired platform ↔ end-user invoice (subscription or meta). */
    public function pairedInvoice(): ?BillingInvoice
    {
        $pairedId = $this->meta['paired_invoice_id'] ?? null;
        if ($pairedId) {
            return BillingInvoice::query()->find((int) $pairedId);
        }

        if (! $this->subscription_id) {
            return null;
        }

        $subscription = $this->relationLoaded('subscription')
            ? $this->subscription
            : $this->subscription()->first();

        if (! $subscription) {
            return null;
        }

        if ($this->isPlatformType()) {
            $paired = $subscription->relationLoaded('clientInvoice')
                ? $subscription->clientInvoice
                : ($subscription->client_invoice_id
                    ? BillingInvoice::query()->find($subscription->client_invoice_id)
                    : null);
        } else {
            $paired = $subscription->relationLoaded('platformInvoice')
                ? $subscription->platformInvoice
                : ($subscription->platform_invoice_id
                    ? BillingInvoice::query()->find($subscription->platform_invoice_id)
                    : null);
        }

        return $paired;
    }

    public function pairedInvoiceNo(): ?string
    {
        if (! empty($this->meta['paired_invoice_no'])) {
            return (string) $this->meta['paired_invoice_no'];
        }

        return $this->pairedInvoice()?->invoice_no;
    }
}
