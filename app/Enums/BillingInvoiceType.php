<?php

namespace App\Enums;

enum BillingInvoiceType: string
{
    case Platform = 'platform';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Platform => __('app.billing.invoice_type_platform'),
            self::Client => __('app.billing.invoice_type_client'),
        };
    }

    public function subscriptionSummaryLabel(): string
    {
        return match ($this) {
            self::Platform => __('app.billing.platform_invoice_summary'),
            self::Client => __('app.billing.end_user_invoice_summary'),
        };
    }

    public function amountColumnLabel(): string
    {
        return match ($this) {
            self::Platform => __('app.billing.company_plan_price'),
            self::Client => __('app.billing.end_user_selling_price'),
        };
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Platform => 'PINV',
            self::Client => 'CINV',
        };
    }
}
