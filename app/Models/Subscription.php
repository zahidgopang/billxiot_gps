<?php

namespace App\Models;

use App\Enums\SubscriptionType;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'plan',
        'subscription_plan_id',
        'client_id',
        'starts_at',
        'ends_at',
        'status',
        'subscription_type',
        'company_price',
        'selling_price',
        'device_unit_cost',
        'device_selling_price',
        'platform_invoice_id',
        'client_invoice_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'company_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'device_unit_cost' => 'decimal:2',
        'device_selling_price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function histories()
    {
        return $this->hasMany(SubscriptionHistory::class)->orderByDesc('archived_at');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function platformInvoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'platform_invoice_id');
    }

    public function clientInvoice()
    {
        return $this->belongsTo(BillingInvoice::class, 'client_invoice_id');
    }

    public function subscriptionProfit(): float
    {
        return max(0, (float) $this->selling_price - (float) $this->company_price);
    }

    public function deviceProfit(): float
    {
        return max(0, (float) $this->device_selling_price - (float) $this->device_unit_cost);
    }

    public function subscriptionTypeEnum(): SubscriptionType
    {
        return SubscriptionType::tryFrom((string) ($this->subscription_type ?? ''))
            ?? SubscriptionType::Renew;
    }

    public function isNewSubscriptionType(): bool
    {
        return $this->subscriptionTypeEnum() === SubscriptionType::New;
    }

    public function isEffectivelyExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->status === 'active' && $this->ends_at) {
            return $this->ends_at->endOfDay()->isPast();
        }

        return false;
    }
}
