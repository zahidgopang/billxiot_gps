<?php

namespace App\Enums;

enum BillingLineType: string
{
    case Subscription = 'subscription';
    case Device = 'device';
    case Adjustment = 'adjustment';
    case NotificationEmail = 'notification_email';
    case NotificationWhatsApp = 'notification_whatsapp';
}
