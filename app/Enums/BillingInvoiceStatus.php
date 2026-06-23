<?php

namespace App\Enums;

enum BillingInvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Due = 'due';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        $key = 'app.billing.status_' . $this->value;

        return __($key) !== $key ? __($key) : str_replace('_', ' ', ucfirst($this->value));
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Partial => 'info',
            self::Overdue => 'danger',
            self::Cancelled => 'secondary',
            self::Unpaid, self::Due => 'warning',
        };
    }

    /**
     * @return list<string>
     */
    public static function forType(BillingInvoiceType $type): array
    {
        return match ($type) {
            BillingInvoiceType::Platform => [
                self::Unpaid->value,
                self::Partial->value,
                self::Paid->value,
                self::Overdue->value,
                self::Cancelled->value,
            ],
            BillingInvoiceType::Client => [
                self::Due->value,
                self::Partial->value,
                self::Paid->value,
                self::Cancelled->value,
            ],
        };
    }

    public static function defaultFor(BillingInvoiceType $type): self
    {
        return match ($type) {
            BillingInvoiceType::Platform => self::Unpaid,
            BillingInvoiceType::Client => self::Due,
        };
    }
}
