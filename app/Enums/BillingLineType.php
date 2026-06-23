<?php

namespace App\Enums;

enum BillingLineType: string
{
    case Subscription = 'subscription';
    case Device = 'device';
    case Adjustment = 'adjustment';
}
