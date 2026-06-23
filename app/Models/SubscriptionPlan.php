<?php

namespace App\Models;

use App\Enums\PlanBillingCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'billing_cycle',
        'duration_months',
        'company_price',
        'currency',
        'features',
        'status',
        'is_public',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'company_price' => 'decimal:2',
        'features' => 'array',
        'is_public' => 'boolean',
        'duration_months' => 'integer',
        'sort_order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function billingCycleEnum(): PlanBillingCycle
    {
        return PlanBillingCycle::tryFrom((string) $this->billing_cycle)
            ?? PlanBillingCycle::tryFromDurationMonths((int) $this->duration_months);
    }

    public function billingCycleLabel(): string
    {
        return $this->billingCycleEnum()->label();
    }

    public function formattedPrice(): string
    {
        return number_format((float) $this->company_price, 2) . ' ' . $this->currency;
    }

    public function displayLabel(): string
    {
        return $this->name . ' (' . $this->billingCycleLabel() . ') — ' . $this->formattedPrice();
    }

    public static function generateUniqueSlug(string $name, string $billingCycle, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'plan';
        }
        $base .= '-' . $billingCycle;

        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    public function scopePublicActive($query)
    {
        return $query->where('status', 'active')->where('is_public', true)->orderBy('sort_order');
    }
}
