<?php

namespace App\Enums;

enum SubscriptionType: string
{
    case New = 'new';
    case Renew = 'renew';

    public function label(): string
    {
        return match ($this) {
            self::New => __('app.billing.subscription_type_new'),
            self::Renew => __('app.billing.subscription_type_renew'),
        };
    }
}
